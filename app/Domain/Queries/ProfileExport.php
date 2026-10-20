<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MovementStatus;
use App\Models\ActivityEntry;
use App\Models\Attachment;
use App\Models\CommitmentSubject;
use App\Models\FinancialProfile;
use App\Models\Instalment;
use App\Models\InstalmentAllocation;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\ObligationShare;
use App\Models\Party;
use App\Models\QuantityReturn;
use App\Models\Record;
use App\Models\RecordParty;
use App\Models\RecordShare;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;

final class ProfileExport
{
    public const int ROW_CAP = 2000;

    public function __construct(
        private readonly ProfileTotals $profileTotals,
        private readonly OutstandingQuantity $outstandingQuantity,
        private readonly RecordVisibilityQuery $recordVisibilityQuery,
    ) {}

    public function rowCount(FinancialProfile $profile, User $viewer): int
    {
        $recordIds = $this->visibleRecordIds($profile, $viewer);
        $obligationIds = Obligation::query()->whereIn('record_id', clone $recordIds)->select('id');

        return (clone $recordIds)->count()
            + (clone $obligationIds)->count()
            + MoneyMovement::query()->whereIn('obligation_id', clone $obligationIds)->whereIn('status', [MovementStatus::Confirmed->value, MovementStatus::Voided->value])->count()
            + QuantityReturn::query()->whereIn('obligation_id', clone $obligationIds)->whereIn('status', [MovementStatus::Confirmed->value, MovementStatus::Voided->value])->count()
            + ObligationShare::query()->whereIn('obligation_id', clone $obligationIds)->count()
            + Instalment::query()->whereIn('obligation_id', clone $obligationIds)->count()
            + InstalmentAllocation::query()->whereIn('instalment_id', Instalment::query()->whereIn('obligation_id', clone $obligationIds)->select('id'))->count()
            + RecordShare::query()->whereIn('record_id', clone $recordIds)->count()
            + $this->attachmentQuery($profile, $viewer)->count()
            + $this->activityQuery($profile, $viewer)->count();
    }

    /** @return array<string, mixed> */
    public function payload(FinancialProfile $profile, User $viewer): array
    {
        $records = $this->visibleRecords($profile, $viewer)
            ->with([
                'partyLinks.party',
                'creator',
                'shares.creator',
                'obligations.moneySubject',
                'obligations.quantitySubject',
                'obligations.commitmentSubject',
                'obligations.chargeTerms.fixedTotalAgreedBy',
                'obligations.shares.party',
                'obligations.instalments.allocations',
                'obligations.moneyMovements' => function (Relation $query): void {
                    $query->whereIn('status', [MovementStatus::Confirmed->value, MovementStatus::Voided->value])->with('attachments');
                },
                'obligations.quantityReturns' => function (Relation $query): void {
                    $query->whereIn('status', [MovementStatus::Confirmed->value, MovementStatus::Voided->value])->with('attachments');
                },
            ])
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return [
            'version' => 2,
            'profile' => [
                'id' => $profile->getKey(),
                'name' => $profile->name,
                'base_currency' => $profile->base_currency,
                'timezone' => $profile->timezone,
                'interest_free_mode' => $profile->interest_free_mode,
                'show_hijri' => $profile->show_hijri,
            ],
            'totals' => $this->profileTotals->forProfile($profile, $viewer),
            'parties' => $this->partyDirectory($records),
            'records' => $records->map(fn (Record $record): array => $this->record($record))->values()->all(),
            'attachments' => $this->attachmentManifest($profile, $viewer),
            'activity' => $this->activities($profile, $viewer),
        ];
    }

    public function toJson(FinancialProfile $profile, User $viewer): string
    {
        return json_encode($this->payload($profile, $viewer), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /** @return list<Attachment> */
    public function attachmentFiles(FinancialProfile $profile, User $viewer): array
    {
        return array_values($this->attachmentQuery($profile, $viewer)
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->all());
    }

    public function toCsv(FinancialProfile $profile, User $viewer): string
    {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('The export stream could not be opened.');
        }

        fputcsv($handle, ['type', 'id', 'record_id', 'obligation_id', 'title', 'direction', 'status', 'currency', 'amount_minor', 'occurred_on', 'quantity', 'attachment_url', 'actor', 'before', 'after']);
        $payload = $this->payload($profile, $viewer);
        foreach (['to_pay', 'to_receive'] as $bucket) {
            foreach ($payload['totals'][$bucket] as $currency => $amount) {
                fputcsv($handle, ['total', $bucket, '', '', '', '', '', $currency, $amount, '', '', '']);
            }
        }

        foreach ($payload['records'] as $record) {
            fputcsv($handle, ['record', $record['id'], '', '', $record['title'], '', $record['is_archived'] ? 'archived' : 'active', '', '', '', '', '']);
            foreach ($record['obligations'] as $obligation) {
                fputcsv($handle, ['obligation', $obligation['id'], $record['id'], '', $obligation['title'], $obligation['direction'], $obligation['status'], $obligation['currency'] ?? '', '', $obligation['due_on'] ?? '', $obligation['quantity']['total'] ?? '', '']);
                foreach ($obligation['confirmed_movements'] as $movement) {
                    fputcsv($handle, ['movement', $movement['id'], $record['id'], $obligation['id'], '', $obligation['direction'], $movement['status'], $movement['currency'], $movement['amount_minor'], $movement['occurred_on'], '', '']);
                }
                foreach ($obligation['confirmed_returns'] as $return) {
                    fputcsv($handle, ['return', $return['id'], $record['id'], $obligation['id'], '', $obligation['direction'], 'confirmed', '', '', $return['occurred_on'], $return['quantity'], '']);
                }
            }
        }
        foreach ($payload['attachments'] as $attachment) {
            fputcsv($handle, ['attachment', $attachment['id'], '', '', $attachment['original_name'], '', '', '', '', $attachment['created_at'], '', $attachment['url']]);
        }
        foreach ($payload['activity'] as $activity) {
            fputcsv($handle, [
                'activity',
                $activity['id'],
                '',
                '',
                $activity['action'],
                '',
                '',
                '',
                '',
                $activity['occurred_at'],
                '',
                '',
                $activity['actor'],
                json_encode($activity['before'], JSON_THROW_ON_ERROR),
                json_encode($activity['after'], JSON_THROW_ON_ERROR),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /** @param Collection<int, Record> $records
     * @return list<array{id: string, name: string, kind: string, status: string}>
     */
    private function partyDirectory(Collection $records): array
    {
        $parties = [];
        foreach ($records as $record) {
            foreach ($record->partyLinks as $link) {
                if ($link->party instanceof Party) {
                    $parties[(string) $link->party->getKey()] = [
                        'id' => (string) $link->party->getKey(),
                        'name' => $link->party->display_name,
                        'kind' => $link->party->kind->value,
                        'status' => $link->party->status->value,
                    ];
                }
            }
            foreach ($record->obligations as $obligation) {
                foreach ($obligation->shares as $share) {
                    if ($share->party instanceof Party) {
                        $parties[(string) $share->party->getKey()] = [
                            'id' => (string) $share->party->getKey(),
                            'name' => $share->party->display_name,
                            'kind' => $share->party->kind->value,
                            'status' => $share->party->status->value,
                        ];
                    }
                }
            }
        }
        ksort($parties);

        return array_values($parties);
    }

    /** @return array<string, mixed> */
    private function record(Record $record): array
    {
        return [
            'id' => $record->getKey(),
            'title' => $record->title,
            'note' => $record->note,
            'is_archived' => $record->is_archived,
            'visibility' => $record->visibility->value,
            'created_by_name' => $record->creator?->name,
            'parties' => $record->partyLinks->sortBy('id')->map(fn (RecordParty $link): array => [
                'id' => $link->party?->getKey(),
                'name' => $link->party?->display_name,
                'kind' => $link->party?->kind->value,
                'role' => $link->role->value,
                'is_primary' => $link->is_primary,
            ])->values()->all(),
            'shares' => $record->shares->map(fn (RecordShare $share): array => [
                'id' => $share->getKey(),
                'created_by_name' => $share->creator?->name,
                'expires_at' => $share->expires_at?->toIso8601String(),
                'acknowledged_at' => $share->acknowledged_at?->toIso8601String(),
                'acknowledged_name' => $share->acknowledged_name,
                'disputed_at' => $share->disputed_at?->toIso8601String(),
                'disputed_name' => $share->disputed_name,
                'dispute_note' => $share->dispute_note,
            ])->values()->all(),
            'obligations' => $record->obligations->map(fn (Obligation $obligation): array => $this->obligation($obligation))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function obligation(Obligation $obligation): array
    {
        $quantity = $obligation->quantitySubject === null
            ? null
            : $this->outstandingQuantity->forObligation($obligation);

        return [
            'id' => $obligation->getKey(),
            'title' => $obligation->title,
            'direction' => $obligation->direction->value,
            'status' => $obligation->status->value,
            'closure_reason' => $obligation->closure_reason?->value,
            'subject_type' => $obligation->subject_type->value,
            'due_on' => $obligation->due_on?->toDateString(),
            'currency' => $obligation->moneySubject?->currency,
            'quantity' => $quantity === null ? null : [
                'name' => $obligation->quantitySubject->name,
                'total' => $obligation->quantitySubject->total,
                'returned' => $quantity['returned'],
                'unit' => $obligation->quantitySubject->unit,
                'is_fractionable' => $obligation->quantitySubject->is_fractionable,
            ],
            'commitment' => $obligation->commitmentSubject === null ? null : [
                'done_criteria' => $obligation->commitmentSubject->done_criteria,
                'completed_at' => $obligation->commitmentSubject->completed_at?->toIso8601String(),
                'completion_note' => $obligation->commitmentSubject->completion_note,
            ],
            'charge_terms' => $obligation->chargeTerms === null ? null : [
                'mode' => $obligation->chargeTerms->mode,
                'kind' => $obligation->chargeTerms->kind,
                'rate' => $obligation->chargeTerms->rate,
                'basis' => $obligation->chargeTerms->basis,
                'period' => $obligation->chargeTerms->period,
                'grace_days' => $obligation->chargeTerms->grace_days,
                'cap_minor' => $obligation->chargeTerms->cap_minor,
                'total_payable_minor' => $obligation->chargeTerms->total_payable_minor,
                'penalty_kind' => $obligation->chargeTerms->penalty_kind,
                'penalty_value' => $obligation->chargeTerms->penalty_value,
                'allocation_order' => $obligation->chargeTerms->allocation_order,
                'is_formal' => $obligation->chargeTerms->is_formal,
                'auto_apply_on_acceptance' => $obligation->chargeTerms->auto_apply_on_acceptance,
                'next_charge_on' => $obligation->chargeTerms->next_charge_on?->toDateString(),
                'last_charge_on' => $obligation->chargeTerms->last_charge_on?->toDateString(),
                'accepted_at' => $obligation->chargeTerms->accepted_at?->toIso8601String(),
                'accepted_name' => $obligation->chargeTerms->accepted_name,
                'fixed_total_agreed_upfront_at' => $obligation->chargeTerms->fixed_total_agreed_upfront_at?->toIso8601String(),
                'fixed_total_agreed_upfront_by_name' => $obligation->chargeTerms->fixedTotalAgreedBy?->name,
                'fixed_total_posted_at' => $obligation->chargeTerms->fixed_total_posted_at?->toIso8601String(),
            ],
            'obligation_shares' => $obligation->shares->map(fn (ObligationShare $share): array => [
                'party_id' => $share->party_id,
                'party_name' => $share->party?->display_name,
                'party_kind' => $share->party?->kind->value,
                'share_bps' => $share->share_bps,
            ])->values()->all(),
            'instalments' => $obligation->instalments->map(fn (Instalment $instalment): array => [
                'id' => $instalment->getKey(),
                'sequence' => $instalment->sequence,
                'due_on' => $instalment->due_on->toDateString(),
                'amount_minor' => $instalment->amount_minor,
                'currency' => $instalment->currency,
                'paid_minor' => $instalment->paid_minor,
                'allocations' => $instalment->allocations->map(static fn (InstalmentAllocation $allocation): array => [
                    'movement_id' => $allocation->money_movement_id,
                    'amount_minor' => $allocation->amount_minor,
                ])->values()->all(),
            ])->values()->all(),
            'confirmed_movements' => $obligation->moneyMovements->map(fn (MoneyMovement $movement): array => [
                'id' => $movement->getKey(),
                'entry' => $movement->entry->value,
                'status' => $movement->status->value,
                'amount_minor' => $movement->amount_minor,
                'currency' => $movement->currency,
                'occurred_on' => $movement->occurred_on->toDateString(),
                'note' => $movement->note,
                'void_reason' => $movement->void_reason,
                'period_key' => $movement->period_key,
                'calculation' => $movement->calculation,
                'linked_movement_id' => $movement->linked_movement_id,
                'attachments' => $movement->attachments->modelKeys(),
            ])->values()->all(),
            'confirmed_returns' => $obligation->quantityReturns->map(fn (QuantityReturn $return): array => [
                'id' => $return->getKey(),
                'quantity' => $return->quantity,
                'occurred_on' => $return->occurred_on->toDateString(),
                'note' => $return->note,
                'status' => $return->status->value,
                'void_reason' => $return->void_reason,
                'attachments' => $return->attachments->modelKeys(),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function attachmentManifest(FinancialProfile $profile, User $viewer): array
    {
        return array_values($this->attachmentQuery($profile, $viewer)
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->map(fn (Attachment $attachment): array => [
                'id' => $attachment->getKey(),
                'parent_type' => $attachment->attachable_type,
                'parent_id' => $attachment->attachable_id,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size_bytes' => $attachment->size_bytes,
                'category' => $attachment->category->value,
                'link_url' => $attachment->link_url,
                'created_at' => $attachment->created_at->toIso8601String(),
                'url' => URL::temporarySignedRoute('attachments.download', now()->addDays(7), [
                    'profile' => $profile,
                    'attachment' => $attachment,
                ]),
            ])
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function activities(FinancialProfile $profile, User $viewer): array
    {
        return array_values($this->activityQuery($profile, $viewer)
            ->with('actor')
            ->oldest('occurred_at')
            ->oldest('id')
            ->get()
            ->map(fn (ActivityEntry $activity): array => [
                'id' => $activity->getKey(),
                'action' => $activity->action,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'actor' => $activity->actor?->name,
                'before' => $activity->before,
                'after' => $activity->after,
                'occurred_at' => $activity->occurred_at->toIso8601String(),
            ])
            ->values()
            ->all());
    }

    /** @return Builder<Record> */
    private function visibleRecords(FinancialProfile $profile, User $viewer): Builder
    {
        return $this->recordVisibilityQuery->forUser($profile->records()->getQuery(), $viewer);
    }

    /** @return Builder<Record> */
    private function visibleRecordIds(FinancialProfile $profile, User $viewer): Builder
    {
        return $this->visibleRecords($profile, $viewer)->select('records.id');
    }

    /** @return Builder<Obligation> */
    private function visibleObligationIds(FinancialProfile $profile, User $viewer): Builder
    {
        return Obligation::query()
            ->whereIn('record_id', $this->visibleRecordIds($profile, $viewer))
            ->select('obligations.id');
    }

    /** @return Builder<Attachment> */
    private function attachmentQuery(FinancialProfile $profile, User $viewer): Builder
    {
        $recordIds = $this->visibleRecordIds($profile, $viewer);
        $obligationIds = $this->visibleObligationIds($profile, $viewer);
        $movementIds = MoneyMovement::query()->whereIn('obligation_id', clone $obligationIds)->select('money_movements.id');
        $returnIds = QuantityReturn::query()->whereIn('obligation_id', clone $obligationIds)->select('quantity_returns.id');

        return $profile->attachments()->getQuery()
            ->where(function (Builder $query) use ($profile, $recordIds, $obligationIds, $movementIds, $returnIds): void {
                $query->where(function (Builder $parent) use ($profile): void {
                    $parent->where('attachable_type', (new FinancialProfile)->getMorphClass())
                        ->where('attachable_id', $profile->getKey());
                })->orWhere(function (Builder $parent) use ($recordIds): void {
                    $parent->where('attachable_type', (new Record)->getMorphClass())
                        ->whereIn('attachable_id', clone $recordIds);
                })->orWhere(function (Builder $parent) use ($obligationIds): void {
                    $parent->where('attachable_type', (new Obligation)->getMorphClass())
                        ->whereIn('attachable_id', clone $obligationIds);
                })->orWhere(function (Builder $parent) use ($movementIds): void {
                    $parent->where('attachable_type', (new MoneyMovement)->getMorphClass())
                        ->whereIn('attachable_id', clone $movementIds);
                })->orWhere(function (Builder $parent) use ($returnIds): void {
                    $parent->where('attachable_type', (new QuantityReturn)->getMorphClass())
                        ->whereIn('attachable_id', clone $returnIds);
                });
            });
    }

    /** @return Builder<ActivityEntry> */
    private function activityQuery(FinancialProfile $profile, User $viewer): Builder
    {
        $recordIds = $this->visibleRecordIds($profile, $viewer);
        $obligationIds = $this->visibleObligationIds($profile, $viewer);
        $chargeTermIds = ObligationChargeTerm::query()->whereIn('obligation_id', clone $obligationIds)->select('obligation_charge_terms.obligation_id');
        $recordPartyIds = RecordParty::query()->whereIn('record_id', clone $recordIds)->select('record_parties.id');
        $movementIds = MoneyMovement::query()->whereIn('obligation_id', clone $obligationIds)->select('money_movements.id');
        $returnIds = QuantityReturn::query()->whereIn('obligation_id', clone $obligationIds)->select('quantity_returns.id');
        $reminderIds = Reminder::query()->whereIn('obligation_id', clone $obligationIds)->select('reminders.id');
        $shareIds = RecordShare::query()->whereIn('record_id', clone $recordIds)->select('record_shares.id');
        $attachmentIds = $this->attachmentQuery($profile, $viewer)->select('attachments.id');
        $privateSubjectTypes = [
            (new Record)->getMorphClass(),
            (new Obligation)->getMorphClass(),
            (new ObligationChargeTerm)->getMorphClass(),
            (new RecordParty)->getMorphClass(),
            (new MoneyMovement)->getMorphClass(),
            (new QuantityReturn)->getMorphClass(),
            (new CommitmentSubject)->getMorphClass(),
            (new Reminder)->getMorphClass(),
            (new RecordShare)->getMorphClass(),
            (new Attachment)->getMorphClass(),
        ];

        return $profile->activityEntries()->getQuery()
            ->where(function (Builder $query) use ($privateSubjectTypes, $recordIds, $obligationIds, $chargeTermIds, $recordPartyIds, $movementIds, $returnIds, $reminderIds, $shareIds, $attachmentIds): void {
                $query->whereNotIn('subject_type', $privateSubjectTypes)
                    ->orWhere(function (Builder $visible) use ($recordIds, $obligationIds, $chargeTermIds, $recordPartyIds, $movementIds, $returnIds, $reminderIds, $shareIds, $attachmentIds): void {
                        $visible->where(function (Builder $subjects) use ($recordIds, $obligationIds, $chargeTermIds, $recordPartyIds, $movementIds, $returnIds, $reminderIds, $shareIds, $attachmentIds): void {
                            $subjects->where(function (Builder $subject) use ($recordIds): void {
                                $subject->where('subject_type', (new Record)->getMorphClass())
                                    ->whereIn('subject_id', clone $recordIds);
                            })->orWhere(function (Builder $subject) use ($obligationIds): void {
                                $subject->whereIn('subject_type', [
                                    (new Obligation)->getMorphClass(),
                                    (new CommitmentSubject)->getMorphClass(),
                                ])->whereIn('subject_id', clone $obligationIds);
                            })->orWhere(function (Builder $subject) use ($chargeTermIds): void {
                                $subject->where('subject_type', (new ObligationChargeTerm)->getMorphClass())
                                    ->whereIn('subject_id', clone $chargeTermIds);
                            })->orWhere(function (Builder $subject) use ($recordPartyIds): void {
                                $subject->where('subject_type', (new RecordParty)->getMorphClass())
                                    ->whereIn('subject_id', clone $recordPartyIds);
                            })->orWhere(function (Builder $subject) use ($movementIds): void {
                                $subject->where('subject_type', (new MoneyMovement)->getMorphClass())
                                    ->whereIn('subject_id', clone $movementIds);
                            })->orWhere(function (Builder $subject) use ($returnIds): void {
                                $subject->where('subject_type', (new QuantityReturn)->getMorphClass())
                                    ->whereIn('subject_id', clone $returnIds);
                            })->orWhere(function (Builder $subject) use ($reminderIds): void {
                                $subject->where('subject_type', (new Reminder)->getMorphClass())
                                    ->whereIn('subject_id', clone $reminderIds);
                            })->orWhere(function (Builder $subject) use ($shareIds): void {
                                $subject->where('subject_type', (new RecordShare)->getMorphClass())
                                    ->whereIn('subject_id', clone $shareIds);
                            })->orWhere(function (Builder $subject) use ($attachmentIds): void {
                                $subject->where('subject_type', (new Attachment)->getMorphClass())
                                    ->whereIn('subject_id', clone $attachmentIds);
                            });
                        });
                    });
            });
    }
}
