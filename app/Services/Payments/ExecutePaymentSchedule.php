<?php

namespace App\Services\Payments;

use App\Actions\Obligations\RecordTransaction;
use App\Models\PaymentAuthorisation;
use App\Models\PaymentExecutionAttempt;
use App\Models\PaymentSchedule;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExecutePaymentSchedule
{
    public function __construct(private readonly PaymentProviderManager $providers, private readonly RecordTransaction $recordTransaction, private readonly AuditLogger $auditLogger, private readonly ActivityNotifier $activityNotifier) {}

    public function handle(PaymentSchedule $schedule): ?PaymentExecutionAttempt
    {
        $claim = DB::transaction(function () use ($schedule): array {
            $lockedSchedule = PaymentSchedule::query()
                ->with(['obligation.record.profile', 'authorisations'])
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSchedule->next_runs_on !== null && $lockedSchedule->next_runs_on->isFuture()) {
                return ['outcome' => 'not_due', 'attempt' => $lockedSchedule->executionAttempts()->latest()->first()];
            }

            if ($lockedSchedule->obligation->currentPositionDirection() !== 'payable') {
                $idempotencyKey = $this->idempotencyKey($lockedSchedule);
                $attempt = PaymentExecutionAttempt::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'payment_schedule_id' => $lockedSchedule->getKey(),
                        'provider' => 'none',
                        'amount' => $lockedSchedule->amount,
                        'currency' => $lockedSchedule->currency,
                        'status' => 'blocked_position_changed',
                        'attempted_at' => now(),
                    ],
                );
                if ($attempt->wasRecentlyCreated) {
                    $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'payment_blocked_position_changed', 'Payment schedule paused for review', 'A scheduled payment was blocked because the current position no longer requires a payment.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);
                }

                return ['outcome' => 'settled', 'attempt' => $attempt];
            }

            $authorisation = $lockedSchedule->authorisations->first(fn ($item): bool => $item->status === 'approved' && $item->revoked_at === null);
            $idempotencyKey = $this->idempotencyKey($lockedSchedule);
            $attempt = PaymentExecutionAttempt::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'payment_schedule_id' => $lockedSchedule->getKey(),
                    'payment_authorisation_id' => $authorisation?->getKey(),
                    'provider' => $authorisation === null ? 'none' : $authorisation->provider,
                    'amount' => $lockedSchedule->amount,
                    'currency' => $lockedSchedule->currency,
                    'status' => $authorisation === null ? 'requires_approval' : 'pending',
                    'attempted_at' => now(),
                ],
            );
            if ($attempt->status !== 'pending' && ! $this->isStaleClaim($attempt)) {
                if ($attempt->wasRecentlyCreated && $attempt->status === 'requires_approval') {
                    $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment is waiting for your explicit approval before it can run.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);
                }

                return ['outcome' => 'settled', 'attempt' => $attempt];
            }

            if ($authorisation === null || ($authorisation->max_amount !== null && (int) $lockedSchedule->amount > (int) $authorisation->max_amount)) {
                $updated = $attempt->updateQuietly(['status' => 'requires_approval']);
                if ($updated) {
                    $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment needs approval because its authorisation is missing or its limit is too low.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);
                }

                return ['outcome' => 'settled', 'attempt' => $updated ? $attempt->refresh() : $attempt];
            }

            // Claim the attempt before charging: the provider call runs outside
            // any database transaction so row locks are never held across HTTP.
            $claimed = PaymentExecutionAttempt::query()
                ->whereKey($attempt->getKey())
                ->whereIn('status', ['pending', 'processing'])
                ->lockForUpdate()
                ->firstOrFail();
            if ($claimed->status === 'processing' && ! $this->isStaleClaim($claimed)) {
                return ['outcome' => 'settled', 'attempt' => $claimed];
            }
            $claimed->update(['status' => 'processing', 'attempted_at' => now()]);

            return [
                'outcome' => 'charge',
                'attempt' => $claimed,
                'schedule_id' => $lockedSchedule->getKey(),
                'authorisation_id' => $authorisation->getKey(),
                'idempotency_key' => $idempotencyKey,
            ];
        });

        if ($claim['outcome'] !== 'charge') {
            return $claim['attempt'];
        }

        try {
            $result = $this->providers->charge(
                PaymentSchedule::query()->with('obligation.record.profile')->whereKey($claim['schedule_id'])->firstOrFail(),
                PaymentAuthorisation::query()->whereKey($claim['authorisation_id'])->firstOrFail(),
                $claim['idempotency_key']
            );
        } catch (Throwable $exception) {
            report($exception);

            return DB::transaction(function () use ($claim): PaymentExecutionAttempt {
                $attempt = PaymentExecutionAttempt::query()->with('schedule.obligation.record.profile')->whereKey($claim['attempt']->getKey())->lockForUpdate()->firstOrFail();
                if ($attempt->status === 'processing') {
                    $attempt->update(['status' => 'failed', 'error_message' => 'Payment provider execution failed.', 'completed_at' => now()]);
                    $this->activityNotifier->notifyObligation($attempt->schedule->obligation, 'automatic_payment_failed', 'Automatic payment needs attention', 'A scheduled payment could not be completed. Review the provider status before trying again.', priority: 'urgent', context: ['currency' => $attempt->currency, 'amount' => (string) $attempt->amount]);
                }

                return $attempt->refresh();
            });
        }

        return DB::transaction(function () use ($claim, $result): PaymentExecutionAttempt {
            $attempt = PaymentExecutionAttempt::query()->whereKey($claim['attempt']->getKey())->lockForUpdate()->firstOrFail();
            if ($attempt->status !== 'processing') {
                return $attempt->refresh();
            }

            $lockedSchedule = PaymentSchedule::query()
                ->with(['obligation.record.profile', 'authorisations'])
                ->whereKey($claim['schedule_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $authorisation = $lockedSchedule->authorisations->firstWhere('id', $claim['authorisation_id']);
            if ($authorisation === null || ($authorisation->max_amount !== null && (int) $lockedSchedule->amount > (int) $authorisation->max_amount)) {
                $attempt->update(['status' => 'requires_approval', 'completed_at' => now()]);
                $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment needs approval because its authorisation is missing or its limit is too low.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);

                return $attempt->refresh();
            }
            $transaction = $this->recordTransaction->handleSystem($lockedSchedule->obligation, ['status' => 'confirmed', 'amount' => '0', 'amount_minor' => (int) $lockedSchedule->amount, 'currency' => $lockedSchedule->currency, 'occurred_on' => today()->toDateString(), 'external_reference' => $result['external_reference'], 'note' => 'Automatic payment through '.$authorisation->provider.' provider.']);
            $attempt->update(['financial_transaction_id' => $transaction->getKey(), 'status' => 'succeeded', 'external_reference' => $result['external_reference'], 'response_payload' => $result['payload'], 'completed_at' => now()]);
            $lockedSchedule->update(['next_runs_on' => $this->nextRun($lockedSchedule)]);
            $this->auditLogger->record($lockedSchedule->obligation->record->profile, null, PaymentExecutionAttempt::class, $attempt->getKey(), 'succeeded', after: $attempt->only(['status', 'external_reference', 'completed_at']));

            return $attempt->refresh();
        });
    }

    private function isStaleClaim(PaymentExecutionAttempt $attempt): bool
    {
        if ($attempt->status !== 'processing' || $attempt->attempted_at === null) {
            return false;
        }

        return CarbonImmutable::parse($attempt->attempted_at)->lt(now()->subMinutes(15));
    }

    private function idempotencyKey(PaymentSchedule $schedule): string
    {
        return $schedule->getKey().':'.($schedule->next_runs_on?->toDateString() ?? today()->toDateString());
    }

    private function nextRun(PaymentSchedule $schedule): string
    {
        $date = CarbonImmutable::parse((string) ($schedule->next_runs_on ?? today()));

        return match ($schedule->frequency) {
            'weekly' => $date->addWeek()->toDateString(),
            'quarterly' => $date->addMonths(3)->toDateString(),
            default => $date->addMonth()->toDateString(),
        };
    }
}
