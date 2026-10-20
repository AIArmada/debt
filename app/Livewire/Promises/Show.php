<?php

namespace App\Livewire\Promises;

use App\Actions\Promises\AddObligation;
use App\Actions\Promises\AddRecordGuarantor;
use App\Actions\Promises\ArchiveRecord;
use App\Actions\Promises\AttachEvidence;
use App\Actions\Promises\CompleteCommitment;
use App\Actions\Promises\ConfirmProposedMovement;
use App\Actions\Promises\CreateRecordShare;
use App\Actions\Promises\Data\AddObligationData;
use App\Actions\Promises\Data\AttachEvidenceData;
use App\Actions\Promises\Data\CompleteCommitmentData;
use App\Actions\Promises\Data\CreateRecordShareData;
use App\Actions\Promises\Data\GenerateInstalmentScheduleData;
use App\Actions\Promises\Data\RecordGuarantorData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Actions\Promises\Data\ReduceObligationData;
use App\Actions\Promises\Data\ReopenCommitmentData;
use App\Actions\Promises\Data\SaveChargeTermsData;
use App\Actions\Promises\Data\SaveNoteData;
use App\Actions\Promises\Data\ScheduleReminderData;
use App\Actions\Promises\Data\SetObligationSharesData;
use App\Actions\Promises\Data\SetRecordVisibilityData;
use App\Actions\Promises\Data\SettleObligationData;
use App\Actions\Promises\Data\SnoozeReminderData;
use App\Actions\Promises\Data\UpdatePromiseDetailsData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\DeleteRecord;
use App\Actions\Promises\DetachEvidence;
use App\Actions\Promises\DismissProposedMovement;
use App\Actions\Promises\DismissReminder;
use App\Actions\Promises\DisputeObligation;
use App\Actions\Promises\ForgiveObligation;
use App\Actions\Promises\GenerateInstalmentSchedule;
use App\Actions\Promises\PauseObligation;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\RegenerateInstalments;
use App\Actions\Promises\RemoveRecordGuarantor;
use App\Actions\Promises\ReopenCommitment;
use App\Actions\Promises\ResolveDispute;
use App\Actions\Promises\RestoreRecord;
use App\Actions\Promises\ResumeObligation;
use App\Actions\Promises\ReturnQuantity;
use App\Actions\Promises\RevokeRecordShare;
use App\Actions\Promises\SaveChargeTerms;
use App\Actions\Promises\SaveNote;
use App\Actions\Promises\ScheduleReminder;
use App\Actions\Promises\SetObligationShares;
use App\Actions\Promises\SetRecordVisibility;
use App\Actions\Promises\SettleObligation;
use App\Actions\Promises\SnoozeReminder;
use App\Actions\Promises\UpdatePromiseDetails;
use App\Actions\Promises\VoidMovement;
use App\Actions\Promises\WriteOffObligation;
use App\Domain\Communication\MessageTemplates;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\SubjectType;
use App\Domain\Money\LargestRemainderAllocator;
use App\Domain\Money\Money;
use App\Domain\Presenters\HijriDatePresenter;
use App\Domain\Queries\BalanceBreakdown;
use App\Domain\Queries\ChargeSchedulePreview;
use App\Domain\Queries\ChargeTermsHash;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\OutstandingQuantity;
use App\Domain\Queries\PromiseShowData;
use App\Domain\Queries\RecordActivity;
use App\Domain\Queries\RecordStatusQuery;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\Record;
use App\Models\User;
use App\Services\GenerateCharges;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class Show extends Component
{
    use WithFileUploads;

    public FinancialProfile $profile;

    public Record $record;

    public bool $editingDetails = false;

    public string $promiseTitle = '';

    public ?string $promiseDueOn = null;

    public string $partialAmount = '';

    public string $movementNote = '';

    public string $recordNote = '';

    public RecordVisibility $visibility = RecordVisibility::Shared;

    public string $forgiveAmount = '';

    public string $writeOffAmount = '';

    public string $shareUrl = '';

    public string $quantityReturn = '';

    public string $quantityReturnedOn = '';

    public string $completionNote = '';

    public string $reopenReason = '';

    public string $partDirection = Direction::Payable->value;

    public string $partSubjectType = SubjectType::Money->value;

    public string $partAmount = '';

    public string $partQuantityName = '';

    public string $partQuantityTotal = '';

    public string $partQuantityUnit = '';

    public bool $partIsFractionable = false;

    public string $partDoneCriteria = '';

    public ?string $partDueOn = null;

    public string $partNote = '';

    public string $reminderDate = '';

    public int $instalmentCount = 3;

    public string $instalmentFrequency = 'monthly';

    public ?string $instalmentFirstDueOn = null;

    public string $instalmentPayDayOfMonth = '';

    public string $reminderChannel = 'database';

    public string $messageTemplate = 'gentle_reminder';

    public string $messageLocale = 'en';

    public string $voidReason = 'Recorded in error';

    public string $manualChargeEntry = 'fee';

    public string $manualChargeAmount = '';

    public string $manualChargeOccurredOn = '';

    public string $manualChargeNote = '';

    public string $chargeCurrency = 'MYR';

    public string $chargeMode = 'none';

    public string $chargeKind = 'none';

    public string $chargeRate = '';

    public ?string $chargeBasis = null;

    public string $chargePeriod = 'monthly';

    public int $chargeGraceDays = 0;

    public string $chargeCap = '';

    public string $chargeTotalPayable = '';

    public string $chargePenaltyKind = 'none';

    public string $chargePenaltyValue = '';

    public string $chargeAllocationOrder = 'charges_first';

    public bool $chargeIsFormal = false;

    public bool $chargeAutoApplyOnAcceptance = false;

    public bool $fixedTotalAgreedUpfront = false;

    public ?TemporaryUploadedFile $evidenceFile = null;

    public string $evidenceLinkUrl = '';

    public string $evidenceCategory = AttachmentCategory::Other->value;

    /** @var array<string, list<array{partyId: string, percentage: string}>> */
    /** @var array<string, list<array{partyId: string, percentage: string}>> */
    public array $shareRows = [];

    public string $guarantorPartyId = '';

    public string $newGuarantorName = '';

    public bool $showEvidenceForm = false;

    public ?string $evidenceMovementId = null;

    public ?string $evidenceReturnId = null;

    public function mount(FinancialProfile $profile, Record $record): void
    {
        $this->profile = $profile;
        $this->record = $record;
        $this->recordNote = (string) ($record->note ?? '');
        $this->visibility = $record->visibility;
        $this->promiseTitle = $record->title;
        $this->promiseDueOn = $record->obligations()->oldest('created_at')->oldest('id')->value('due_on');
        $this->quantityReturnedOn = today()->toDateString();
        $this->reminderDate = today()->addDay()->toDateString();
        $this->instalmentFirstDueOn = today()->addMonthNoOverflow()->toDateString();
        abort_unless(Gate::allows('view', $record), 404);
        $this->manualChargeOccurredOn = today()->toDateString();
        $this->loadChargeForm();
        foreach ($record->obligations()->with('shares')->get() as $obligation) {
            $this->shareRows[$obligation->getKey()] = array_values($obligation->shares
                ->sortBy('party_id')
                ->map(static fn ($share): array => [
                    'partyId' => (string) $share->party_id,
                    'percentage' => intdiv((int) $share->share_bps, 100).'.'.str_pad((string) ((int) $share->share_bps % 100), 2, '0', STR_PAD_LEFT),
                ])
                ->values()
                ->all());
        }
    }

    public function updatedChargeTotalPayable(string $value): void
    {
        $this->fixedTotalAgreedUpfront = false;
    }

    public function saveVisibility(SetRecordVisibility $setRecordVisibility): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $setRecordVisibility->handle($user, $this->record, SetRecordVisibilityData::fromInput([
            'visibility' => $this->visibility->value,
        ]));
    }

    public function addShareRow(string $obligationId): void
    {
        Gate::authorize('update', $this->record);
        $this->shareRows[$obligationId] ??= [];
        $this->shareRows[$obligationId][] = ['partyId' => '', 'percentage' => ''];
    }

    public function removeShareRow(string $obligationId, int $index): void
    {
        Gate::authorize('update', $this->record);
        $rows = [];
        foreach ($this->shareRows[$obligationId] ?? [] as $rowIndex => $row) {
            if ($rowIndex !== $index) {
                $rows[] = $row;
            }
        }
        $this->shareRows[$obligationId] = $rows;
    }

    public function saveShares(string $obligationId, SetObligationShares $setObligationShares): void
    {
        $obligation = $this->obligationForRecord($obligationId);
        $rows = $this->shareRows[$obligationId] ?? [];
        $shares = [];

        foreach ($rows as $index => $row) {
            if ($row['partyId'] === '' && $row['percentage'] === '') {
                continue;
            }
            if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $row['percentage'], $matches) !== 1) {
                throw ValidationException::withMessages(["shareRows.{$obligationId}.{$index}.percentage" => __('promises.share_percent_invalid')]);
            }
            $basisPoints = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
            if ($basisPoints < 1 || $basisPoints > 10000 || $row['partyId'] === '') {
                throw ValidationException::withMessages(["shareRows.{$obligationId}.{$index}.percentage" => __('promises.share_percent_invalid')]);
            }
            $shares[] = ['partyId' => $row['partyId'], 'shareBps' => $basisPoints];
        }

        $setObligationShares->handle($this->user(), $obligation, SetObligationSharesData::fromInput(['shares' => $shares]));
        $this->record->refresh();
        $this->record->load('partyLinks.party');
    }

    public function addGuarantor(AddRecordGuarantor $addRecordGuarantor): void
    {
        $addRecordGuarantor->handle($this->user(), $this->record, RecordGuarantorData::fromInput([
            'partyId' => $this->guarantorPartyId === '' ? null : $this->guarantorPartyId,
            'partyName' => $this->newGuarantorName,
        ]));
        $this->reset(['guarantorPartyId', 'newGuarantorName']);
        $this->record->load('partyLinks.party');
    }

    public function removeGuarantor(string $linkId, RemoveRecordGuarantor $removeRecordGuarantor): void
    {
        $link = $this->record->partyLinks()->whereKey($linkId)->where('role', PartyRole::Guarantor->value)->firstOrFail();
        $removeRecordGuarantor->handle($this->user(), $link);
        $this->record->load('partyLinks.party');
    }

    public function createShare(CreateRecordShare $createRecordShare): void
    {
        $result = $createRecordShare->handle($this->user(), $this->record, CreateRecordShareData::fromInput([]));
        $this->shareUrl = route('shares.show', ['token' => $result['token']]);
        $this->record->refresh();
    }

    public function revokeShare(string $shareId, RevokeRecordShare $revokeRecordShare): void
    {
        $share = $this->record->shares()->whereKey($shareId)->firstOrFail();
        $revokeRecordShare->handle($this->user(), $share);
        $this->record->refresh();
    }

    public function pauseObligation(string $obligationId, PauseObligation $pauseObligation): void
    {
        $pauseObligation->handle($this->user(), $this->obligationForRecord($obligationId));
        $this->record->refresh();
    }

    public function resumeObligation(string $obligationId, ResumeObligation $resumeObligation): void
    {
        $resumeObligation->handle($this->user(), $this->obligationForRecord($obligationId));
        $this->record->refresh();
    }

    public function disputeObligation(string $obligationId, DisputeObligation $disputeObligation): void
    {
        $disputeObligation->handle($this->user(), $this->obligationForRecord($obligationId));
        $this->record->refresh();
    }

    public function resolveDispute(string $obligationId, ResolveDispute $resolveDispute): void
    {
        $resolveDispute->handle($this->user(), $this->obligationForRecord($obligationId));
        $this->record->refresh();
    }

    public function forgiveBalance(ForgiveObligation $forgiveObligation): void
    {
        $obligation = $this->moneyObligation();
        $forgiveObligation->handle($this->user(), $obligation, ReduceObligationData::fromInput([
            'amount' => $this->forgiveAmount,
            'note' => $this->movementNote,
        ], (string) $obligation->moneySubject->currency));
        $this->forgiveAmount = '';
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function writeOffBalance(WriteOffObligation $writeOffObligation): void
    {
        $obligation = $this->moneyObligation();
        $writeOffObligation->handle($this->user(), $obligation, ReduceObligationData::fromInput([
            'amount' => $this->writeOffAmount,
            'note' => $this->movementNote,
        ], (string) $obligation->moneySubject->currency));
        $this->writeOffAmount = '';
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function saveNote(SaveNote $saveNote): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $saveNote->handle($user, $this->record, SaveNoteData::fromInput([
            'note' => $this->recordNote,
        ]));
    }

    public function beginEditingDetails(): void
    {
        Gate::authorize('update', $this->record);
        $this->promiseTitle = $this->record->title;
        $this->promiseDueOn = $this->record->obligations()->oldest('created_at')->oldest('id')->value('due_on');
        $this->editingDetails = true;
    }

    public function updateDetails(UpdatePromiseDetails $updatePromiseDetails): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $updatePromiseDetails->handle($user, $this->record, UpdatePromiseDetailsData::fromInput([
            'recordId' => $this->record->getKey(),
            'obligationId' => $this->record->obligations()->oldest('created_at')->oldest('id')->value('id'),
            'title' => $this->promiseTitle,
            'dueOn' => $this->promiseDueOn,
        ]));
        $this->editingDetails = false;
        $this->record->load('obligations');
    }

    public function delete(DeleteRecord $deleteRecord): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $deleteRecord->handle($user, $this->record);
        $this->redirectRoute('promises.index', ['profile' => $this->profile], navigate: true);
    }

    public function restart(DeleteRecord $deleteRecord, PromiseShowData $promiseShowData): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $query = $promiseShowData->restartQuery($this->record);
        $deleteRecord->handle($user, $this->record);
        $this->redirectRoute('promises.create', ['profile' => $this->profile] + $query, navigate: true);
    }

    public function addAnotherLikeThis(PromiseShowData $promiseShowData): void
    {
        Gate::authorize('createObligation', $this->profile);
        $query = $promiseShowData->restartQuery($this->record);
        $this->redirectRoute('promises.create', ['profile' => $this->profile] + $query, navigate: true);
    }

    public function archive(ArchiveRecord $archiveRecord): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $archiveRecord->handle($user, $this->record);
    }

    public function restore(RestoreRecord $restoreRecord): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $restoreRecord->handle($user, $this->record);
    }

    public function prepareRecordEvidence(): void
    {
        $this->showEvidenceForm = true;
        $this->evidenceMovementId = null;
        $this->evidenceReturnId = null;
    }

    public function prepareMovementEvidence(string $movementId): void
    {
        $this->showEvidenceForm = true;
        $this->evidenceMovementId = $movementId;
        $this->evidenceReturnId = null;
    }

    public function prepareReturnEvidence(string $returnId): void
    {
        $this->showEvidenceForm = true;
        $this->evidenceMovementId = null;
        $this->evidenceReturnId = $returnId;
    }

    public function evidenceTargetDescription(): string
    {
        if (! $this->showEvidenceForm) {
            return 'Choose Add evidence or Attach from history to add a file or link.';
        }

        return $this->evidenceMovementId !== null || $this->evidenceReturnId !== null
            ? 'Evidence will be attached to the selected history item.'
            : 'Evidence will be attached to this promise.';
    }

    public function saveEvidence(AttachEvidence $attachEvidence, PromiseShowData $promiseShowData): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $attachEvidence->handle($user, $promiseShowData->evidenceParent($this->record, $this->evidenceMovementId, $this->evidenceReturnId), AttachEvidenceData::fromInput([
            'linkUrl' => $this->evidenceLinkUrl,
            'category' => $this->evidenceCategory,
        ], $this->evidenceFile));
        $this->reset(['evidenceFile', 'evidenceLinkUrl']);
        $this->evidenceCategory = AttachmentCategory::Other->value;
        $this->showEvidenceForm = false;
        $this->evidenceMovementId = null;
        $this->evidenceReturnId = null;
        $this->record->refresh();
    }

    public function detachEvidence(string $attachmentId, DetachEvidence $detachEvidence): void
    {
        $attachment = $this->profile->attachments()->whereKey($attachmentId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $detachEvidence->handle($user, $attachment);
        $this->record->refresh();
    }

    public function recordPartial(RecordMoneyMovement $recordMovement, OutstandingBalance $outstandingBalance): void
    {
        $obligation = $this->moneyObligation();
        $currency = (string) $obligation->moneySubject->currency;

        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $data = RecordMovementData::settlementFromInput([
            'amount' => $this->partialAmount,
            'note' => $this->movementNote,
        ], $currency, today()->toDateString());
        $recordMovement->handle($user, $obligation, $data);
        $outstandingBalance->forget($obligation);
        $this->partialAmount = '';
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function recordManualCharge(RecordMoneyMovement $recordMovement): void
    {
        $this->validate([
            'manualChargeEntry' => ['required', 'in:interest_charge,profit_charge,fee,penalty'],
            'manualChargeAmount' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'manualChargeOccurredOn' => ['required', 'date_format:Y-m-d'],
            'manualChargeNote' => ['nullable', 'string', 'max:4000'],
        ]);

        $obligation = $this->moneyObligation();
        try {
            $money = Money::parseMajor($this->manualChargeAmount, (string) $obligation->moneySubject->currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['manualChargeAmount' => $exception->getMessage()]);
        }
        if ($money->amountMinor <= 0) {
            throw ValidationException::withMessages(['manualChargeAmount' => 'Enter a positive charge amount.']);
        }

        $entry = MoneyEntry::from($this->manualChargeEntry);
        $recordMovement->handle($this->user(), $obligation, RecordMovementData::confirmed(
            $money->amountMinor,
            $money->currency,
            $entry,
            $this->manualChargeOccurredOn,
            $this->manualChargeNote === '' ? null : $this->manualChargeNote,
            activityAction: 'manual_charge_recorded',
        ));
        $this->manualChargeAmount = '';
        $this->manualChargeNote = '';
        $this->record->refresh();
    }

    public function saveChargeTerms(SaveChargeTerms $saveChargeTerms): void
    {
        $obligation = $this->moneyObligation();
        $saveChargeTerms->handle($this->user(), $obligation, SaveChargeTermsData::fromInput([
            'currency' => $this->chargeCurrency,
            'mode' => $this->chargeMode,
            'kind' => $this->chargeKind,
            'rate' => $this->chargeRate,
            'basis' => $this->chargeBasis,
            'period' => $this->chargePeriod,
            'graceDays' => $this->chargeGraceDays,
            'cap' => $this->chargeCap,
            'totalPayable' => $this->chargeTotalPayable,
            'penaltyKind' => $this->chargePenaltyKind,
            'penaltyValue' => $this->chargePenaltyValue,
            'allocationOrder' => $this->chargeAllocationOrder,
            'isFormal' => $this->chargeIsFormal,
            'autoApplyOnAcceptance' => $this->chargeAutoApplyOnAcceptance,
            'fixedTotalAgreedUpfront' => $this->fixedTotalAgreedUpfront,
        ]));
        $this->record->refresh();
        $this->loadChargeForm();
    }

    public function generateChargesNow(GenerateCharges $generateCharges): void
    {
        $obligation = $this->moneyObligation();
        $generateCharges->forObligation($obligation, now($this->profile->timezone));
        $this->record->refresh();
    }

    public function confirmProposedCharge(string $movementId, ConfirmProposedMovement $confirmMovement): void
    {
        $movement = $this->moneyObligation()->moneyMovements()->whereKey($movementId)->firstOrFail();
        $confirmMovement->handle($this->user(), $movement);
        $this->record->refresh();
    }

    public function dismissProposedCharge(string $movementId, DismissProposedMovement $dismissMovement): void
    {
        $movement = $this->moneyObligation()->moneyMovements()->whereKey($movementId)->firstOrFail();
        $dismissMovement->handle($this->user(), $movement);
        $this->record->refresh();
    }

    public function recordQuantityReturn(ReturnQuantity $returnQuantity): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $returnQuantity->handle($user, $this->quantityObligation(), RecordQuantityReturnData::fromInput([
            'quantity' => $this->quantityReturn,
            'returnedOn' => $this->quantityReturnedOn,
            'note' => $this->movementNote,
        ]));
        $this->quantityReturn = '';
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function markSettled(SettleObligation $settleObligation): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $settleObligation->handle($user, $this->moneyObligation(), SettleObligationData::fromInput([
            'note' => $this->movementNote,
        ]));
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function completeCommitment(CompleteCommitment $completeCommitment): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $completeCommitment->handle($user, $this->commitmentObligation(), CompleteCommitmentData::fromInput([
            'note' => $this->completionNote,
        ]));
        $this->completionNote = '';
        $this->record->refresh();
    }

    public function reopenCommitment(ReopenCommitment $reopenCommitment): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $reopenCommitment->handle($user, $this->commitmentObligation(), ReopenCommitmentData::fromInput([
            'reason' => $this->reopenReason,
        ]));
        $this->reopenReason = '';
        $this->record->refresh();
    }

    public function addObligation(AddObligation $addObligation): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $addObligation->handle($user, $this->record, AddObligationData::fromInput([
            'direction' => $this->partDirection,
            'subjectType' => $this->partSubjectType,
            'amount' => $this->partAmount,
            'quantityName' => $this->partQuantityName,
            'quantityTotal' => $this->partQuantityTotal,
            'quantityUnit' => $this->partQuantityUnit,
            'isFractionable' => $this->partIsFractionable,
            'doneCriteria' => $this->partDoneCriteria,
            'dueOn' => $this->partDueOn,
            'note' => $this->partNote,
        ], (string) $this->profile->base_currency));
        $this->reset([
            'partAmount', 'partQuantityName', 'partQuantityTotal', 'partQuantityUnit',
            'partDoneCriteria', 'partDueOn', 'partNote',
        ]);
        $this->partDirection = Direction::Payable->value;
        $this->partSubjectType = SubjectType::Money->value;
        $this->partIsFractionable = false;
        $this->record->refresh();
    }

    public function scheduleReminder(ScheduleReminder $scheduleReminder): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $scheduleReminder->handle($user, $this->primaryObligation(), ScheduleReminderData::fromInput([
            'remindOn' => $this->reminderDate,
            'channel' => $this->reminderChannel,
        ]));
        $this->record->refresh();
    }

    public function requestPauseMessage(): void
    {
        Gate::authorize('update', $this->record);
        $this->messageTemplate = 'hardship_request';
    }

    public function generateInstalments(GenerateInstalmentSchedule $generateInstalmentSchedule): void
    {
        $generateInstalmentSchedule->handle($this->user(), $this->moneyObligation(), $this->instalmentScheduleData());
        $this->record->refresh();
    }

    public function regenerateInstalments(RegenerateInstalments $regenerateInstalments): void
    {
        $regenerateInstalments->handle($this->user(), $this->moneyObligation(), $this->instalmentScheduleData());
        $this->record->refresh();
    }

    public function dismissReminder(string $reminderId, DismissReminder $dismissReminder): void
    {
        $reminder = $this->primaryObligation()->reminders()->whereKey($reminderId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $dismissReminder->handle($user, $reminder);
        $this->record->refresh();
    }

    public function snoozeReminder(string $reminderId, SnoozeReminder $snoozeReminder): void
    {
        $reminder = $this->primaryObligation()->reminders()->whereKey($reminderId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $snoozeReminder->handle($user, $reminder, SnoozeReminderData::fromInput(['until' => $this->reminderDate]));
        $this->record->refresh();
    }

    public function voidMovement(string $movementId, VoidMovement $voidMovement): void
    {
        $movement = $this->moneyObligation()->moneyMovements()->whereKey($movementId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $voidMovement->handle($user, $movement, VoidMovementData::fromInput(['reason' => $this->voidReason]));
        $this->voidReason = 'Recorded in error';
        $this->record->refresh();
    }

    public function render(
        OutstandingBalance $outstandingBalance,
        OutstandingQuantity $outstandingQuantity,
        PromiseShowData $promiseShowData,
        RecordActivity $recordActivity,
        RecordStatusQuery $recordStatus,
        BalanceBreakdown $balanceBreakdown,
        ChargeSchedulePreview $chargeSchedulePreview,
        ChargeTermsHash $chargeTermsHash,
        MessageTemplates $messageTemplates,
        LargestRemainderAllocator $shareAllocator,
        HijriDatePresenter $hijriDatePresenter,
    ): View {
        $obligations = $promiseShowData->obligations($this->record);
        $this->record->loadMissing('attachments', 'partyLinks.party');
        $obligation = $obligations->firstOrFail();
        $hijriDueDates = [];
        if ($this->profile->show_hijri) {
            foreach ($obligations as $part) {
                if ($part->due_on !== null) {
                    $hijriDueDates[$part->getKey()] = $hijriDatePresenter->format($part->due_on);
                }
            }
        }
        $chargeObligation = $obligations->first(fn (Obligation $part): bool => $part->subject_type === SubjectType::Money);
        $balances = $obligation->subject_type === SubjectType::Money
            ? $outstandingBalance->forObligation($obligation)
            : [];
        $quantityPosition = $obligation->subject_type === SubjectType::Quantity
            ? $outstandingQuantity->forObligation($obligation)
            : null;
        $positions = $obligation->subject_type === SubjectType::Money
            ? $promiseShowData->positions($obligation, $balances)
            : [];
        $shareAmounts = [];
        foreach ($obligations as $part) {
            if ($part->shares->isEmpty() || $part->subject_type !== SubjectType::Money || $part->moneySubject === null) {
                continue;
            }
            $shareAmounts[$part->getKey()] = $shareAllocator->allocate(
                abs($outstandingBalance->forObligation($part)[$part->moneySubject->currency] ?? 0),
                array_values($part->shares->sortBy('party_id')->map(static fn ($share): array => [
                    'party_id' => (string) $share->party_id,
                    'share_bps' => (int) $share->share_bps,
                ])->values()->all()),
            );
        }
        $balanceBreakdowns = $chargeObligation instanceof Obligation
            ? $balanceBreakdown->forObligation($chargeObligation)
            : [];
        $chargeTerms = $chargeObligation?->chargeTerms;
        $chargeTermsAccepted = false;
        $chargeTermsGuardedAccrual = false;
        $chargeTermsGuardedPenalty = false;
        $chargePreview = [];
        $proposedCharges = collect();
        $messagePreview = '';
        $whatsAppUrl = null;
        $instalments = $chargeObligation instanceof Obligation
            ? $chargeObligation->instalments()->with('allocations')->get()
            : collect();
        $instalmentTotal = (int) $instalments->sum('amount_minor');
        $instalmentPaid = (int) $instalments->sum('paid_minor');
        $canRegenerateInstalments = $instalments->isNotEmpty()
            && $chargeObligation?->status === ObligationStatus::Open
            && $instalments->every(static fn ($instalment): bool => $instalment->paid_minor === 0 && $instalment->allocations->isEmpty());

        if ($chargeObligation instanceof Obligation && $chargeObligation->moneySubject !== null) {
            $messagePreview = $messageTemplates->renderFor(
                $this->messageLocale,
                $this->messageTemplate,
                $this->record,
                $chargeObligation,
                $this->shareUrl === '' ? null : $this->shareUrl,
            );
            $primaryParty = $this->record->partyLinks()
                ->where('role', PartyRole::Counterparty->value)
                ->where('is_primary', true)
                ->with('party.contacts')
                ->first()?->party;
            $primaryContact = $primaryParty?->contacts->first(fn ($contact): bool => $contact->is_primary);
            if ($primaryContact !== null) {
                $phone = $this->whatsAppNumber((string) $primaryContact->value);
                if ($phone !== null) {
                    $whatsAppUrl = 'https://wa.me/'.$phone.'?text='.rawurlencode($messagePreview);
                }
            }

            $chargeTermsGuardedAccrual = $this->profile->interest_free_mode
                && $chargeTerms instanceof ObligationChargeTerm
                && ($chargeTerms->kind === 'interest'
                    || ($chargeTerms->kind === 'profit' && $chargeTerms->basis !== 'fixed_total'));
            $chargeTermsGuardedPenalty = $this->profile->interest_free_mode
                && $chargeTerms instanceof ObligationChargeTerm
                && $chargeTerms->penalty_kind === 'percent_of_overdue';

            if ($chargeTerms instanceof ObligationChargeTerm && $chargeTerms->accepted_terms_hash !== null) {
                $currentTermsHash = $chargeTermsHash->forObligation($chargeObligation);
                $chargeTermsAccepted = hash_equals((string) $chargeTerms->accepted_terms_hash, $currentTermsHash);
            }

            $previewTerms = new ObligationChargeTerm([
                'obligation_id' => $chargeObligation->getKey(),
                'mode' => $this->chargeMode,
                'kind' => $this->chargeKind,
                'rate' => $this->chargeRate === '' ? null : $this->chargeRate,
                'basis' => $this->chargeBasis,
                'period' => $this->chargePeriod,
                'grace_days' => $this->chargeGraceDays,
                'cap_minor' => null,
                'total_payable_minor' => null,
                'allocation_order' => $this->chargeAllocationOrder,
                'fixed_total_posted_at' => $chargeTerms?->fixed_total_posted_at,
                'last_charge_on' => $chargeTerms?->last_charge_on,
                'next_charge_on' => $chargeTerms?->next_charge_on,
            ]);
            if ($this->chargeCap !== '') {
                try {
                    $previewTerms->forceFill([
                        'cap_minor' => Money::parseMajor($this->chargeCap, (string) $chargeObligation->moneySubject->currency)->amountMinor,
                    ]);
                } catch (\InvalidArgumentException) {
                    $previewTerms->forceFill(['cap_minor' => null]);
                }
            }
            if ($this->chargeTotalPayable !== '') {
                try {
                    $previewTerms->forceFill([
                        'total_payable_minor' => Money::parseMajor($this->chargeTotalPayable, (string) $chargeObligation->moneySubject->currency)->amountMinor,
                    ]);
                } catch (\InvalidArgumentException) {
                    $previewTerms->forceFill(['total_payable_minor' => null]);
                }
            }
            $proposedCharges = $chargeObligation->moneyMovements->where('status', MovementStatus::Proposed)->values();
            if (in_array($this->chargeMode, ['auto_propose', 'auto_apply'], true)) {
                $chargePreview = $chargeSchedulePreview->forTerms(
                    $chargeObligation,
                    $previewTerms,
                    (string) $chargeObligation->moneySubject->currency,
                    now($this->profile->timezone),
                );
            }
        }

        return view('livewire.promises.show', [
            'obligation' => $obligation,
            'positions' => $positions,
            'quantityPosition' => $quantityPosition,
            'obligations' => $obligations,
            'movements' => $obligation->moneyMovements,
            'chargeObligation' => $chargeObligation,
            'chargeTerms' => $chargeTerms,
            'chargeTermsAccepted' => $chargeTermsAccepted,
            'chargeTermsGuardedAccrual' => $chargeTermsGuardedAccrual,
            'chargeTermsGuardedPenalty' => $chargeTermsGuardedPenalty,
            'chargePreview' => $chargePreview,
            'proposedCharges' => $proposedCharges,
            'balanceBreakdowns' => $balanceBreakdowns,
            'returns' => $obligation->quantityReturns,
            'activities' => $recordActivity->forRecord($this->record),
            'recordStatus' => $recordStatus->forRecord($this->record),
            'shares' => $this->record->shares()->latest('created_at')->get(),
            'canDeleteRecord' => $this->record->canBeDeleted(),
            'messagePreview' => $messagePreview,
            'whatsAppUrl' => $whatsAppUrl,
            'messageTemplates' => MessageTemplates::KEYS,
            'reminderChannels' => ReminderChannel::cases(),
            'instalments' => $instalments,
            'instalmentTotal' => $instalmentTotal,
            'instalmentPaid' => $instalmentPaid,
            'canRegenerateInstalments' => $canRegenerateInstalments,
            'shareAmounts' => $shareAmounts,
            'shareParties' => $this->profile->parties()->where('status', 'active')->orderBy('display_name')->get(),
            'guarantors' => $this->record->partyLinks->where('role', PartyRole::Guarantor),
            'hijriDueDates' => $hijriDueDates,
        ])->layout('layouts.app', ['title' => $this->record->title]);
    }

    private function instalmentScheduleData(): GenerateInstalmentScheduleData
    {
        return GenerateInstalmentScheduleData::fromInput([
            'count' => $this->instalmentCount,
            'frequency' => $this->instalmentFrequency,
            'firstDueOn' => $this->instalmentFirstDueOn,
            'payDayOfMonth' => $this->instalmentPayDayOfMonth,
        ]);
    }

    private function whatsAppNumber(string $value): ?string
    {
        $trimmed = trim($value);
        if (preg_match('/^\\+?[0-9\\s().-]+$/D', $trimmed) !== 1) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($this->profile->base_currency === 'MYR' && str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        } elseif ($this->profile->base_currency !== 'MYR' && ! str_starts_with($trimmed, '+')) {
            if (! preg_match('/^[1-9]/', $digits) || strlen($digits) < 11) {
                return null;
            }
        }

        return preg_match('/^[1-9][0-9]{7,14}$/D', $digits) === 1 ? $digits : null;
    }

    private function moneyObligation(): Obligation
    {
        return $this->record->obligations()
            ->where('subject_type', SubjectType::Money->value)
            ->with('moneySubject')
            ->firstOrFail();
    }

    private function loadChargeForm(): void
    {
        $obligation = $this->record->obligations()
            ->where('subject_type', SubjectType::Money->value)
            ->with(['moneySubject', 'chargeTerms'])
            ->first();
        if (! $obligation instanceof Obligation || $obligation->moneySubject === null) {
            return;
        }

        $this->chargeCurrency = (string) $obligation->moneySubject->currency;
        $terms = $obligation->chargeTerms;
        if (! $terms instanceof ObligationChargeTerm) {
            return;
        }

        $this->chargeMode = $terms->mode;
        $this->chargeKind = $terms->kind;
        $this->chargeRate = $terms->rate === null ? '' : (string) $terms->rate;
        $this->chargeBasis = $terms->basis;
        $this->chargePeriod = $terms->period ?? 'monthly';
        $this->chargeGraceDays = (int) $terms->grace_days;
        $this->chargeCap = $terms->cap_minor === null ? '' : $this->minorAsMajor((int) $terms->cap_minor, $this->chargeCurrency);
        $this->chargeTotalPayable = $terms->total_payable_minor === null
            ? ''
            : $this->minorAsMajor((int) $terms->total_payable_minor, $this->chargeCurrency);
        $this->chargePenaltyKind = $terms->penalty_kind;
        $this->chargePenaltyValue = $terms->penalty_value === null
            ? ''
            : ($terms->penalty_kind === 'fixed'
                ? $this->minorAsMajor((int) $terms->penalty_value, $this->chargeCurrency)
                : (string) $terms->penalty_value);
        $this->chargeAllocationOrder = $terms->allocation_order;
        $this->chargeIsFormal = $terms->is_formal;
        $this->chargeAutoApplyOnAcceptance = $terms->auto_apply_on_acceptance;
        $this->fixedTotalAgreedUpfront = $terms->fixed_total_agreed_upfront_at !== null;

        if ($this->profile->interest_free_mode) {
            if ($this->chargeKind === 'interest' || ($this->chargeKind === 'profit' && $this->chargeBasis !== 'fixed_total')) {
                $this->chargeKind = 'none';
                $this->chargeBasis = null;
                $this->chargeRate = '';
            }
            if ($this->chargePenaltyKind === 'percent_of_overdue') {
                $this->chargePenaltyKind = 'none';
                $this->chargePenaltyValue = '';
            }
        }
    }

    private function minorAsMajor(int $amountMinor, string $currency): string
    {
        $precision = Money::precisionFor($currency);
        if ($precision === 0) {
            return (string) $amountMinor;
        }

        $divisor = 10 ** $precision;

        return intdiv($amountMinor, $divisor).'.'.str_pad((string) ($amountMinor % $divisor), $precision, '0', STR_PAD_LEFT);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function primaryObligation(): Obligation
    {
        return $this->record->obligations()->firstOrFail();
    }

    private function obligationForRecord(string $obligationId): Obligation
    {
        return $this->record->obligations()->whereKey($obligationId)->firstOrFail();
    }

    private function quantityObligation(): Obligation
    {
        return $this->record->obligations()
            ->where('subject_type', SubjectType::Quantity->value)
            ->with('quantitySubject')
            ->firstOrFail();
    }

    private function commitmentObligation(): Obligation
    {
        return $this->record->obligations()
            ->where('subject_type', SubjectType::Commitment->value)
            ->with('commitmentSubject')
            ->firstOrFail();
    }
}
