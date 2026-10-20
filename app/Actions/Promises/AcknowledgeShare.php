<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\AcknowledgeShareData;
use App\Domain\Enums\NotificationType;
use App\Domain\Queries\ChargeTermsHash;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\Record;
use App\Models\RecordShare;
use App\Notifications\ShareAccepted as ShareAcceptedNotification;
use App\Services\ActivityLogger;
use App\Services\GenerateCharges;
use App\Services\RecordShareNotificationRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class AcknowledgeShare
{
    public function __construct(
        private readonly ChargeTermsHash $chargeTermsHash,
        private readonly ActivityLogger $activityLogger,
        private readonly RecordShareNotificationRecipients $recipients,
        private readonly GenerateCharges $generateCharges,
    ) {}

    public function handle(RecordShare $share, AcknowledgeShareData $data): RecordShare
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
            $terms = ObligationChargeTerm::query()
                ->whereIn('obligation_id', $obligations->modelKeys())
                ->orderBy('obligation_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('obligation_id');
            $obligations->load('moneySubject');
            $expectedHashes = [];

            foreach ($obligations as $obligation) {
                if ($obligation->moneySubject !== null && $terms->has($obligation->getKey())) {
                    $obligation->setRelation('chargeTerms', $terms->get($obligation->getKey()));
                    $expectedHashes[$obligation->getKey()] = $this->chargeTermsHash->forObligation($obligation);
                }
            }

            $suppliedHashes = $data->termsHashes;
            ksort($expectedHashes);
            ksort($suppliedHashes);
            if ($expectedHashes !== $suppliedHashes) {
                throw ValidationException::withMessages(['termsHashes' => 'The displayed charge terms have changed. Reload this page and review them again.']);
            }

            if ($lockedShare->disputed_at !== null) {
                throw ValidationException::withMessages(['share' => 'This promise is disputed and cannot be acknowledged.']);
            }

            foreach ($obligations as $obligation) {
                $term = $terms->get($obligation->getKey());
                if (! $term instanceof ObligationChargeTerm || $obligation->moneySubject === null) {
                    continue;
                }

                $hash = $expectedHashes[$obligation->getKey()];
                $term->forceFill([
                    'terms_hash' => $hash,
                    'accepted_terms_hash' => $hash,
                    'accepted_at' => now(),
                    'accepted_name' => $data->name,
                    'mode' => $term->auto_apply_on_acceptance ? 'auto_apply' : $term->mode,
                ])->save();

                if ($term->auto_apply_on_acceptance && $term->basis === 'fixed_total') {
                    $this->generateCharges->forObligation(
                        $obligation,
                        CarbonImmutable::now($record->profile->timezone),
                    );
                }
            }

            $lockedShare->forceFill([
                'acknowledged_at' => now(),
                'acknowledged_name' => $data->name,
            ])->save();
            $this->activityLogger->record(
                $record->profile,
                null,
                $lockedShare,
                'record_share_acknowledged',
                after: [
                    'record_id' => $record->getKey(),
                    'share_id' => $lockedShare->getKey(),
                    'acknowledged_name' => $data->name,
                    'accepted_terms_hashes' => array_values($expectedHashes),
                ],
            );

            $recipients = $this->recipients->forRecord($record, NotificationType::ShareAccepted);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ShareAcceptedNotification([
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
