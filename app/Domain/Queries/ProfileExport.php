<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MovementStatus;
use App\Models\ActivityEntry;
use App\Models\Attachment;
use App\Models\FinancialProfile;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\QuantityReturn;
use App\Models\Record;
use App\Models\RecordParty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;

final class ProfileExport
{
    public const int ROW_CAP = 2000;

    public function __construct(
        private readonly ProfileTotals $profileTotals,
        private readonly OutstandingQuantity $outstandingQuantity,
    ) {}

    public function rowCount(FinancialProfile $profile): int
    {
        $obligations = Obligation::query()->whereHas('record', fn (Builder $query): Builder => $query->where('profile_id', $profile->getKey()));

        return $profile->records()->count()
            + (clone $obligations)->count()
            + MoneyMovement::query()
                ->where('status', MovementStatus::Confirmed->value)
                ->whereHas('obligation.record', fn (Builder $query): Builder => $query->where('profile_id', $profile->getKey()))
                ->count()
            + QuantityReturn::query()
                ->where('status', MovementStatus::Confirmed->value)
                ->whereHas('obligation.record', fn (Builder $query): Builder => $query->where('profile_id', $profile->getKey()))
                ->count()
            + $profile->attachments()->count()
            + $profile->activityEntries()->count();
    }

    /** @return array<string, mixed> */
    public function payload(FinancialProfile $profile): array
    {
        $records = $profile->records()
            ->with([
                'partyLinks.party',
                'obligations.moneySubject',
                'obligations.quantitySubject',
                'obligations.commitmentSubject',
                'obligations.moneyMovements' => function (Relation $query): void {
                    $query->where('status', MovementStatus::Confirmed->value)->with('attachments');
                },
                'obligations.quantityReturns' => function (Relation $query): void {
                    $query->where('status', MovementStatus::Confirmed->value)->with('attachments');
                },
            ])
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return [
            'profile' => [
                'id' => $profile->getKey(),
                'name' => $profile->name,
                'base_currency' => $profile->base_currency,
                'timezone' => $profile->timezone,
            ],
            'totals' => $this->profileTotals->forProfile($profile),
            'records' => $records->map(fn (Record $record): array => $this->record($record))->values()->all(),
            'attachments' => $this->attachmentManifest($profile),
            'activity' => $this->activities($profile),
        ];
    }

    public function toJson(FinancialProfile $profile): string
    {
        return json_encode($this->payload($profile), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function toCsv(FinancialProfile $profile): string
    {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('The export stream could not be opened.');
        }

        fputcsv($handle, ['type', 'id', 'record_id', 'obligation_id', 'title', 'direction', 'status', 'currency', 'amount_minor', 'occurred_on', 'quantity', 'attachment_url', 'actor', 'before', 'after']);
        $payload = $this->payload($profile);
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
                    fputcsv($handle, ['movement', $movement['id'], $record['id'], $obligation['id'], '', $obligation['direction'], 'confirmed', $movement['currency'], $movement['amount_minor'], $movement['occurred_on'], '', '']);
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

    /** @return array<string, mixed> */
    private function record(Record $record): array
    {
        return [
            'id' => $record->getKey(),
            'title' => $record->title,
            'note' => $record->note,
            'is_archived' => $record->is_archived,
            'parties' => $record->partyLinks->map(fn (RecordParty $link): array => [
                'id' => $link->party?->getKey(),
                'name' => $link->party?->display_name,
                'role' => $link->role,
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
            'confirmed_movements' => $obligation->moneyMovements->map(fn (MoneyMovement $movement): array => [
                'id' => $movement->getKey(),
                'entry' => $movement->entry->value,
                'amount_minor' => $movement->amount_minor,
                'currency' => $movement->currency,
                'occurred_on' => $movement->occurred_on->toDateString(),
                'note' => $movement->note,
                'attachments' => $movement->attachments->modelKeys(),
            ])->values()->all(),
            'confirmed_returns' => $obligation->quantityReturns->map(fn (QuantityReturn $return): array => [
                'id' => $return->getKey(),
                'quantity' => $return->quantity,
                'occurred_on' => $return->occurred_on->toDateString(),
                'note' => $return->note,
                'attachments' => $return->attachments->modelKeys(),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function attachmentManifest(FinancialProfile $profile): array
    {
        return array_values($profile->attachments()
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
    private function activities(FinancialProfile $profile): array
    {
        return array_values($profile->activityEntries()
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
}
