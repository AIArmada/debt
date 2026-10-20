<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\DisputeShareData;
use App\Domain\Enums\NotificationType;
use App\Domain\Enums\ObligationStatus;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\RecordShare;
use App\Notifications\ShareDisputed as ShareDisputedNotification;
use App\Services\ActivityLogger;
use App\Services\RecordShareNotificationRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class DisputeShare
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RecordShareNotificationRecipients $recipients,
    ) {}

    public function handle(RecordShare $share, DisputeShareData $data): RecordShare
    {
        return DB::transaction(function () use ($share, $data): RecordShare {
            $lockedShare = RecordShare::query()->whereKey($share->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($lockedShare);
            $record = Record::query()->whereKey($lockedShare->record_id)->lockForUpdate()->firstOrFail();
            $record->loadMissing('profile');
            $obligations = Obligation::query()
                ->where('record_id', $record->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $beforeStatuses = $obligations->mapWithKeys(static fn (Obligation $obligation): array => [
                $obligation->getKey() => $obligation->status->value,
            ])->all();
            $changedObligations = [];

            foreach ($obligations as $obligation) {
                $obligation->forceFill(['status' => ObligationStatus::Disputed])->save();
                $changedObligations[] = $obligation->getKey();
            }

            $lockedShare->forceFill([
                'disputed_at' => now(),
                'disputed_name' => $data->name,
                'dispute_note' => $data->note,
            ])->save();
            $this->activityLogger->record(
                $record->profile,
                null,
                $lockedShare,
                'record_share_disputed',
                before: ['obligation_statuses' => $beforeStatuses],
                after: [
                    'record_id' => $record->getKey(),
                    'share_id' => $lockedShare->getKey(),
                    'disputed_name' => $data->name,
                    'dispute_note' => $data->note,
                    'disputed_obligation_ids' => $changedObligations,
                ],
            );

            $recipients = $this->recipients->forRecord($record, NotificationType::ShareDisputed);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ShareDisputedNotification([
                    'profile_id' => $record->profile->getKey(),
                    'record_id' => $record->getKey(),
                    'title' => $record->title,
                    'share_id' => $lockedShare->getKey(),
                    'actor_name' => $data->name,
                ]));
            }

            return $lockedShare->refresh();
        });
    }

    private function assertActive(RecordShare $share): void
    {
        if ($share->revoked_at !== null || ($share->expires_at !== null && $share->expires_at->isPast())) {
            abort(404);
        }
    }
}
