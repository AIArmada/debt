<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ImportProfileExportData;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\ClosureReason;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Enums\SubjectType;
use App\Domain\Money\Currency;
use App\Domain\Queries\ChargeTermsHash;
use App\Models\Attachment;
use App\Models\FinancialProfile;
use App\Models\Instalment;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

class ImportProfileExport
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ChargeTermsHash $chargeTermsHash,
    ) {}

    public function handle(User $user, ImportProfileExportData $data): FinancialProfile
    {
        $archive = new ZipArchive;
        $archivePath = $data->file->getRealPath();
        if (! is_string($archivePath)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }
        $opened = $archive->open($archivePath);
        if ($opened !== true) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        $storedPaths = [];
        try {
            $json = $archive->getFromName('export.json');
            if (! is_string($json)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            try {
                $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $payload = $this->validatedPayload($decoded);
            $this->validateArchiveEntries($archive, $payload['attachments']);

            try {
                return DB::transaction(function () use ($user, $payload, $archive, &$storedPaths): FinancialProfile {
                    return $this->restore($user, $payload, $archive, $storedPaths);
                });
            } catch (Throwable $exception) {
                Storage::disk('private')->delete($storedPaths);
                throw $exception;
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * @param  array{version: 2, profile: array<string, mixed>, parties: list<array<string, mixed>>, records: list<array<string, mixed>>, attachments: list<array<string, mixed>>}  $payload
     * @param  list<string>  $storedPaths
     */
    private function restore(User $user, array $payload, ZipArchive $archive, array &$storedPaths): FinancialProfile
    {
        $profilePayload = $payload['profile'];
        $baseCurrency = strtoupper($this->requiredString($profilePayload, 'base_currency'));
        $timezone = $this->requiredString($profilePayload, 'timezone');
        if (! Currency::isSupported($baseCurrency) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        $profile = $user->financialProfiles()->create([
            'name' => $this->requiredString($profilePayload, 'name').' '.__('promises.restored_suffix'),
            'base_currency' => $baseCurrency,
            'timezone' => $timezone,
            'is_archived' => false,
            'interest_free_mode' => $this->optionalBool($profilePayload, 'interest_free_mode', true),
            'show_hijri' => $this->optionalBool($profilePayload, 'show_hijri', false),
        ]);
        $profile->members()->forceCreate([
            'user_id' => $user->getKey(),
            'role' => MemberRole::Owner,
            'accepted_at' => now(),
        ]);

        $partyIds = $this->restoreParties($profile, $payload['parties']);
        $maps = [
            'records' => [],
            'obligations' => [],
            'movements' => [],
            'returns' => [],
            'instalments' => [],
        ];
        $pendingMovementLinks = [];
        $pendingAllocations = [];
        $pendingChargeTerms = [];

        foreach ($payload['records'] as $recordData) {
            $record = $this->restoreRecord($profile, $user, $recordData, $partyIds);
            $maps['records'][$this->requiredUuid($recordData, 'id')] = $record;

            foreach ($this->listOfArrays($recordData['obligations'] ?? null) as $obligationData) {
                $obligation = $this->restoreObligation($record, $obligationData);
                $oldObligationId = $this->requiredUuid($obligationData, 'id');
                $maps['obligations'][$oldObligationId] = $obligation;
                $this->restoreObligationShares($obligation, $obligationData, $partyIds);
                $this->restoreInstalments($obligation, $obligationData, $maps, $pendingAllocations);
                $this->restoreMovements($obligation, $obligationData, $user, $maps, $pendingMovementLinks, $pendingAllocations);
                $this->restoreReturns($obligation, $obligationData, $user, $maps);
                $this->restoreSubject($obligation, $obligationData);
                if (is_array($obligationData['charge_terms'] ?? null)) {
                    $pendingChargeTerms[] = [$obligation, $obligationData['charge_terms']];
                }
            }
            $this->restoreRecordShares($record, $recordData, $user);
        }

        $this->restoreMovementLinks($pendingMovementLinks, $maps['movements']);
        $this->restoreInstalmentAllocations($pendingAllocations, $maps['movements'], $maps['instalments']);
        $this->restoreChargeTerms($pendingChargeTerms);
        $this->restoreAttachments(
            $profile,
            $user,
            $this->requiredString($profilePayload, 'id'),
            $payload['attachments'],
            $maps,
            $archive,
            $storedPaths,
        );
        $this->activityLogger->record(
            $profile,
            $user,
            $profile,
            'profile_restored',
            after: ['source_profile_name' => $profilePayload['name']],
        );

        return $profile;
    }

    /** @return array{version: 2, profile: array<string, mixed>, parties: list<array<string, mixed>>, records: list<array<string, mixed>>, attachments: list<array<string, mixed>>} */
    private function validatedPayload(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }
        if (($decoded['version'] ?? null) !== 2) {
            throw ValidationException::withMessages(['file' => __('promises.restore_version_unsupported')]);
        }

        if (! is_array($decoded['profile'] ?? null)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return [
            'version' => 2,
            'profile' => $decoded['profile'],
            'parties' => $this->listOfArrays($decoded['parties'] ?? null),
            'records' => $this->listOfArrays($decoded['records'] ?? null),
            'attachments' => $this->listOfArrays($decoded['attachments'] ?? null),
        ];
    }

    /** @param list<array<string, mixed>> $attachments */
    private function validateArchiveEntries(ZipArchive $archive, array $attachments): void
    {
        $allowedEntries = ['export.json' => true];
        foreach ($attachments as $attachment) {
            if (($attachment['link_url'] ?? null) !== null) {
                continue;
            }
            $id = $this->requiredUuid($attachment, 'id');
            $name = basename($this->requiredString($attachment, 'original_name'));
            $allowedEntries['files/'.$id.'/'.$name] = true;
        }

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            if (! is_string($name)
                || str_starts_with($name, '/')
                || preg_match('#(^|/)\.\.(?:/|$)#', $name) === 1
                || ! isset($allowedEntries[$name])) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            unset($allowedEntries[$name]);
        }
        if ($allowedEntries !== []) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }
    }

    /** @param list<array<string, mixed>> $parties
     * @return array<string, Party>
     */
    private function restoreParties(FinancialProfile $profile, array $parties): array
    {
        $partyIds = [];
        foreach ($parties as $partyData) {
            $oldId = $this->requiredUuid($partyData, 'id');
            $kind = PartyKind::tryFrom($this->requiredString($partyData, 'kind'));
            $status = PartyStatus::tryFrom($this->requiredString($partyData, 'status'));
            if ($kind === null || $status === null || isset($partyIds[$oldId])) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $partyIds[$oldId] = $profile->parties()->create([
                'kind' => $kind,
                'display_name' => $this->requiredString($partyData, 'name'),
                'status' => $status,
            ]);
        }

        return $partyIds;
    }

    /** @param array<string, mixed> $recordData
     * @param  array<string, Party>  $partyIds
     */
    private function restoreRecord(FinancialProfile $profile, User $user, array $recordData, array $partyIds): Record
    {
        $record = $profile->records()->create([
            'created_by' => $user->getKey(),
            'title' => $this->requiredString($recordData, 'title'),
            'note' => $this->optionalString($recordData, 'note'),
            'is_archived' => $this->optionalBool($recordData, 'is_archived', false),
            'visibility' => ($recordData['visibility'] ?? null) === RecordVisibility::Private->value
                ? RecordVisibility::Private
                : RecordVisibility::Shared,
        ]);

        foreach ($this->listOfArrays($recordData['parties'] ?? null) as $linkData) {
            $partyId = $partyIds[$this->requiredUuid($linkData, 'id')] ?? null;
            $role = PartyRole::tryFrom($this->requiredString($linkData, 'role'));
            if (! $partyId instanceof Party || $role === null) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $record->partyLinks()->create([
                'party_id' => $partyId->getKey(),
                'role' => $role,
                'is_primary' => $this->optionalBool($linkData, 'is_primary', false),
            ]);
        }

        return $record;
    }

    /** @param array<string, mixed> $obligationData */
    private function restoreObligation(Record $record, array $obligationData): Obligation
    {
        $direction = Direction::tryFrom($this->requiredString($obligationData, 'direction'));
        $status = ObligationStatus::tryFrom($this->requiredString($obligationData, 'status'));
        $subjectType = SubjectType::tryFrom($this->requiredString($obligationData, 'subject_type'));
        $closure = ($obligationData['closure_reason'] ?? null) === null
            ? null
            : ClosureReason::tryFrom($this->requiredString($obligationData, 'closure_reason'));
        if ($direction === null || $status === null || $subjectType === null
            || (($obligationData['closure_reason'] ?? null) !== null && $closure === null)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $record->obligations()->create([
            'direction' => $direction,
            'title' => $this->requiredString($obligationData, 'title'),
            'status' => $status,
            'closure_reason' => $closure,
            'subject_type' => $subjectType,
            'due_on' => $this->optionalString($obligationData, 'due_on'),
        ]);
    }

    /** @param array<string, mixed> $obligationData */
    private function restoreSubject(Obligation $obligation, array $obligationData): void
    {
        if ($obligation->subject_type === SubjectType::Money) {
            $currency = strtoupper($this->requiredString($obligationData, 'currency'));
            if (! Currency::isSupported($currency)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $obligation->moneySubject()->create(['currency' => $currency]);
        } elseif ($obligation->subject_type === SubjectType::Quantity) {
            $quantity = $this->arrayValue($obligationData, 'quantity');
            $obligation->quantitySubject()->create([
                'name' => $this->requiredString($quantity, 'name'),
                'total' => $this->requiredNumericString($quantity, 'total'),
                'unit' => $this->requiredString($quantity, 'unit'),
                'is_fractionable' => $this->optionalBool($quantity, 'is_fractionable', false),
            ]);
        } else {
            $commitment = $this->arrayValue($obligationData, 'commitment');
            $obligation->commitmentSubject()->create([
                'done_criteria' => $this->requiredString($commitment, 'done_criteria'),
                'completed_at' => $this->optionalString($commitment, 'completed_at'),
                'completion_note' => $this->optionalString($commitment, 'completion_note'),
            ]);
        }
    }

    /** @param array<string, mixed> $obligationData
     * @param  array<string, Party>  $partyIds
     */
    private function restoreObligationShares(Obligation $obligation, array $obligationData, array $partyIds): void
    {
        $shares = $this->listOfArrays($obligationData['obligation_shares'] ?? null);
        $sharePartyIds = [];
        $shareBpsTotal = 0;
        foreach ($shares as $shareData) {
            $party = $partyIds[$this->requiredUuid($shareData, 'party_id')] ?? null;
            $shareBps = $this->requiredInt($shareData, 'share_bps');
            if (! $party instanceof Party || $shareBps < 1 || $shareBps > 10000 || in_array($party->getKey(), $sharePartyIds, true)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $sharePartyIds[] = $party->getKey();
            $shareBpsTotal += $shareBps;
            $obligation->shares()->create(['party_id' => $party->getKey(), 'share_bps' => $shareBps]);
        }
        if ($shares !== [] && $shareBpsTotal !== 10000) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }
    }

    /**
     * @param  array<string, mixed>  $obligationData
     * @param  array<string, mixed>  $maps
     * @param  list<array{instalment_id: string, data: array<string, mixed>}>  $pendingAllocations
     */
    private function restoreInstalments(Obligation $obligation, array $obligationData, array &$maps, array &$pendingAllocations): void
    {
        foreach ($this->listOfArrays($obligationData['instalments'] ?? null) as $instalmentData) {
            $oldId = $this->requiredUuid($instalmentData, 'id');
            $amountMinor = $this->requiredInt($instalmentData, 'amount_minor');
            $paidMinor = $this->requiredInt($instalmentData, 'paid_minor');
            $currency = strtoupper($this->requiredString($instalmentData, 'currency'));
            if ($amountMinor <= 0 || $paidMinor < 0 || $paidMinor > $amountMinor || ! Currency::isSupported($currency)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $instalment = $obligation->instalments()->create([
                'sequence' => $this->requiredInt($instalmentData, 'sequence'),
                'due_on' => $this->requiredString($instalmentData, 'due_on'),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'paid_minor' => $paidMinor,
            ]);
            $maps['instalments'][$oldId] = $instalment;
            foreach ($this->listOfArrays($instalmentData['allocations'] ?? null) as $allocationData) {
                $pendingAllocations[] = ['instalment_id' => $oldId, 'data' => $allocationData];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $obligationData
     * @param  array<string, mixed>  $maps
     * @param  list<array{movement_id: string, linked_movement_id: string}>  $pendingMovementLinks
     * @param  list<array{instalment_id: string, data: array<string, mixed>}>  $pendingAllocations
     */
    private function restoreMovements(Obligation $obligation, array $obligationData, User $user, array &$maps, array &$pendingMovementLinks, array &$pendingAllocations): void
    {
        foreach ($this->listOfArrays($obligationData['confirmed_movements'] ?? null) as $movementData) {
            $oldId = $this->requiredUuid($movementData, 'id');
            $entry = MoneyEntry::tryFrom($this->requiredString($movementData, 'entry'));
            $status = MovementStatus::tryFrom($this->requiredString($movementData, 'status'));
            $currency = strtoupper($this->requiredString($movementData, 'currency'));
            $amountMinor = $this->requiredInt($movementData, 'amount_minor');
            if ($entry === null || ! in_array($status, [MovementStatus::Confirmed, MovementStatus::Voided], true)
                || ! Currency::isSupported($currency) || $amountMinor <= 0) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $calculation = $movementData['calculation'] ?? null;
            if ($calculation !== null && ! is_array($calculation)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $movement = $obligation->moneyMovements()->create([
                'entry' => $entry,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'occurred_on' => $this->requiredString($movementData, 'occurred_on'),
                'status' => $status,
                'void_reason' => $this->optionalString($movementData, 'void_reason'),
                'note' => $this->optionalString($movementData, 'note'),
                'recorded_by' => $user->getKey(),
                'period_key' => $this->restoredPeriodKey($movementData['period_key'] ?? null, (string) $obligationData['id'], (string) $obligation->getKey()),
                'calculation' => $calculation,
            ]);
            $maps['movements'][$oldId] = $movement;
            if (is_string($movementData['linked_movement_id'] ?? null)) {
                $pendingMovementLinks[] = ['movement_id' => $oldId, 'linked_movement_id' => $movementData['linked_movement_id']];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $obligationData
     * @param  array<string, mixed>  $maps
     */
    private function restoreReturns(Obligation $obligation, array $obligationData, User $user, array &$maps): void
    {
        foreach ($this->listOfArrays($obligationData['confirmed_returns'] ?? null) as $returnData) {
            $oldId = $this->requiredUuid($returnData, 'id');
            $status = MovementStatus::tryFrom($this->requiredString($returnData, 'status'));
            if (! in_array($status, [MovementStatus::Confirmed, MovementStatus::Voided], true)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $return = $obligation->quantityReturns()->create([
                'quantity' => $this->requiredNumericString($returnData, 'quantity'),
                'occurred_on' => $this->requiredString($returnData, 'occurred_on'),
                'note' => $this->optionalString($returnData, 'note'),
                'status' => $status,
                'void_reason' => $this->optionalString($returnData, 'void_reason'),
                'recorded_by' => $user->getKey(),
            ]);
            $maps['returns'][$oldId] = $return;
        }
    }

    /** @param list<array{movement_id: string, linked_movement_id: string}> $links
     * @param  array<string, MoneyMovement>  $movements
     */
    private function restoreMovementLinks(array $links, array $movements): void
    {
        foreach ($links as $link) {
            $movement = $movements[$link['movement_id']] ?? null;
            $linkedMovement = $movements[$link['linked_movement_id']] ?? null;
            if (! $movement instanceof MoneyMovement || ! $linkedMovement instanceof MoneyMovement) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $movement->forceFill(['linked_movement_id' => $linkedMovement->getKey()])->save();
        }
    }

    /**
     * @param  list<array{instalment_id: string, data: array<string, mixed>}>  $allocations
     * @param  array<string, MoneyMovement>  $movements
     * @param  array<string, Instalment>  $instalments
     */
    private function restoreInstalmentAllocations(array $allocations, array $movements, array $instalments): void
    {
        foreach ($allocations as $allocation) {
            $movementId = $this->requiredUuid($allocation['data'], 'movement_id');
            $movement = $movements[$movementId] ?? null;
            $instalment = $instalments[$allocation['instalment_id']] ?? null;
            if (! $movement instanceof MoneyMovement || ! $instalment instanceof Instalment) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $amountMinor = $this->requiredInt($allocation['data'], 'amount_minor');
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $instalment->allocations()->create([
                'money_movement_id' => $movement->getKey(),
                'amount_minor' => $amountMinor,
            ]);
        }
    }

    /** @param list<array{0: Obligation, 1: array<string, mixed>}> $terms */
    private function restoreChargeTerms(array $terms): void
    {
        foreach ($terms as [$obligation, $termData]) {
            $mode = $this->requiredString($termData, 'mode');
            $isFormal = $this->optionalBool($termData, 'is_formal', false);
            if ($mode === 'auto_apply' && ! $isFormal) {
                $mode = 'auto_propose';
            }
            $terms = $obligation->chargeTerms()->create([
                'mode' => $mode,
                'kind' => $this->requiredString($termData, 'kind'),
                'rate' => $this->optionalString($termData, 'rate'),
                'basis' => $this->optionalString($termData, 'basis'),
                'period' => $this->optionalString($termData, 'period'),
                'grace_days' => $this->requiredInt($termData, 'grace_days'),
                'cap_minor' => $this->optionalInt($termData, 'cap_minor'),
                'total_payable_minor' => $this->optionalInt($termData, 'total_payable_minor'),
                'penalty_kind' => $this->requiredString($termData, 'penalty_kind'),
                'penalty_value' => $this->optionalInt($termData, 'penalty_value'),
                'allocation_order' => $this->requiredString($termData, 'allocation_order'),
                'is_formal' => $isFormal,
                'auto_apply_on_acceptance' => $this->optionalBool($termData, 'auto_apply_on_acceptance', false),
                'next_charge_on' => $this->optionalString($termData, 'next_charge_on'),
                'last_charge_on' => $this->optionalString($termData, 'last_charge_on'),
                'accepted_terms_hash' => null,
                'accepted_at' => null,
                'accepted_name' => null,
                'fixed_total_agreed_upfront_at' => null,
                'fixed_total_agreed_upfront_by' => null,
                'fixed_total_posted_at' => $this->optionalString($termData, 'fixed_total_posted_at'),
            ]);
            $obligation->setRelation('chargeTerms', $terms);
            $terms->forceFill(['terms_hash' => $this->chargeTermsHash->forObligation($obligation)])->save();
        }
    }

    /** @param array<string, mixed> $recordData */
    private function restoreRecordShares(Record $record, array $recordData, User $user): void
    {
        foreach ($this->listOfArrays($recordData['shares'] ?? null) as $shareData) {
            $record->shares()->create([
                'token_hash' => hash('sha256', Str::random(64)),
                'created_by' => $user->getKey(),
                'expires_at' => $this->optionalString($shareData, 'expires_at'),
                'acknowledged_at' => $this->optionalString($shareData, 'acknowledged_at'),
                'acknowledged_name' => $this->optionalString($shareData, 'acknowledged_name'),
                'disputed_at' => $this->optionalString($shareData, 'disputed_at'),
                'disputed_name' => $this->optionalString($shareData, 'disputed_name'),
                'dispute_note' => $this->optionalString($shareData, 'dispute_note'),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $maps
     * @param  list<string>  $storedPaths
     */
    private function restoreAttachments(FinancialProfile $profile, User $user, string $sourceProfileId, array $attachments, array $maps, ZipArchive $archive, array &$storedPaths): void
    {
        $parents = [$this->morphKey((new FinancialProfile)->getMorphClass(), $sourceProfileId) => $profile];
        foreach (['records', 'obligations', 'movements', 'returns'] as $type) {
            foreach ($maps[$type] as $oldId => $model) {
                $parents[$this->morphKey($model->getMorphClass(), (string) $oldId)] = $model;
            }
        }
        $disk = Storage::disk('private');
        foreach ($attachments as $attachmentData) {
            $parent = $parents[$this->morphKey(
                $this->requiredString($attachmentData, 'parent_type'),
                $this->requiredUuid($attachmentData, 'parent_id'),
            )] ?? null;
            if (! $parent instanceof Model) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            $linkUrl = $this->optionalString($attachmentData, 'link_url');
            $diskPath = null;
            if ($linkUrl === null) {
                $oldId = $this->requiredUuid($attachmentData, 'id');
                $entryName = 'files/'.$oldId.'/'.basename($this->requiredString($attachmentData, 'original_name'));
                $stream = $archive->getStream($entryName);
                if (! is_resource($stream)) {
                    throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
                }
                $directory = 'attachments/'.$profile->getKey().'/'.Str::uuid();
                $extension = preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($entryName, PATHINFO_EXTENSION)) ?? '';
                $filename = Str::uuid().($extension === '' ? '' : '.'.$extension);
                $diskPath = $directory.'/'.$filename;
                $storedPaths[] = $diskPath;
                if (! $disk->put($diskPath, $stream)) {
                    fclose($stream);
                    throw ValidationException::withMessages(['file' => __('promises.restore_file_store_failed')]);
                }
                fclose($stream);
            }
            $category = AttachmentCategory::tryFrom($this->requiredString($attachmentData, 'category'));
            if ($category === null) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
            Attachment::query()->create([
                'profile_id' => $profile->getKey(),
                'attachable_type' => $parent->getMorphClass(),
                'attachable_id' => $parent->getKey(),
                'disk_path' => $diskPath,
                'link_url' => $linkUrl,
                'original_name' => Str::limit(basename($this->requiredString($attachmentData, 'original_name')), 255, ''),
                'mime' => $this->optionalString($attachmentData, 'mime'),
                'size_bytes' => $this->optionalInt($attachmentData, 'size_bytes'),
                'category' => $category,
                'recorded_by' => $user->getKey(),
                'created_at' => $this->optionalString($attachmentData, 'created_at') ?? now(),
            ]);
        }
    }

    private function restoredPeriodKey(mixed $periodKey, string $oldObligationId, string $newObligationId): ?string
    {
        if (! is_string($periodKey) || $periodKey === '') {
            return null;
        }

        if (str_starts_with($periodKey, $oldObligationId.':')) {
            return $newObligationId.substr($periodKey, strlen($oldObligationId));
        }

        return $newObligationId.':restored:'.hash('sha256', $periodKey);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function arrayValue(array $values, string $key): array
    {
        $value = $values[$key] ?? null;
        if (! is_array($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }
        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function requiredUuid(array $values, string $key): string
    {
        $value = $this->requiredString($values, $key);
        if (! Str::isUuid($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function requiredInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (! is_int($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function optionalInt(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_int($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function requiredNumericString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }
        if (! is_numeric((string) $value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $values */
    private function optionalString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function optionalBool(array $values, string $key, bool $default): bool
    {
        $value = $values[$key] ?? $default;
        if (! is_bool($value)) {
            throw ValidationException::withMessages(['file' => __('promises.restore_zip_invalid')]);
        }

        return $value;
    }

    private function morphKey(string $type, string $id): string
    {
        return $type.':'.$id;
    }
}
