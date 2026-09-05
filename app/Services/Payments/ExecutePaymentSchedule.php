<?php

namespace App\Services\Payments;

use App\Actions\Obligations\RecordTransaction;
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

    public function handle(PaymentSchedule $schedule): PaymentExecutionAttempt
    {
        return DB::transaction(function () use ($schedule): PaymentExecutionAttempt {
            $lockedSchedule = PaymentSchedule::query()
                ->with(['obligation.record.profile', 'authorisations'])
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSchedule->next_runs_on !== null && $lockedSchedule->next_runs_on->isFuture()) {
                return $lockedSchedule->executionAttempts()->latest()->firstOrFail();
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

                return $attempt;
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
            if ($attempt->status !== 'pending') {
                if ($attempt->wasRecentlyCreated && $attempt->status === 'requires_approval') {
                    $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment is waiting for your explicit approval before it can run.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);
                }

                return $attempt;
            }

            if ($authorisation === null || ($authorisation->max_amount !== null && (int) $lockedSchedule->amount > (int) $authorisation->max_amount)) {
                $updated = $attempt->updateQuietly(['status' => 'requires_approval']);
                if ($updated) {
                    $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment needs approval because its authorisation is missing or its limit is too low.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);
                }

                return $updated ? $attempt->refresh() : $attempt;
            }

            try {
                $result = $this->providers->charge($lockedSchedule, $authorisation, $idempotencyKey);
                $transaction = $this->recordTransaction->handleSystem($lockedSchedule->obligation, ['status' => 'confirmed', 'amount' => '0', 'amount_minor' => (int) $lockedSchedule->amount, 'currency' => $lockedSchedule->currency, 'occurred_on' => today()->toDateString(), 'external_reference' => $result['external_reference'], 'note' => 'Automatic payment through '.$authorisation->provider.' provider.']);
                $attempt->update(['financial_transaction_id' => $transaction->getKey(), 'status' => 'succeeded', 'external_reference' => $result['external_reference'], 'response_payload' => $result['payload'], 'completed_at' => now()]);
                $lockedSchedule->update(['next_runs_on' => $this->nextRun($lockedSchedule)]);
                $this->auditLogger->record($lockedSchedule->obligation->record->profile, null, PaymentExecutionAttempt::class, $attempt->getKey(), 'succeeded', after: $attempt->only(['status', 'external_reference', 'completed_at']));
            } catch (Throwable $exception) {
                report($exception);
                $attempt->update(['status' => 'failed', 'error_message' => 'Payment provider execution failed.', 'completed_at' => now()]);
                $this->activityNotifier->notifyObligation($lockedSchedule->obligation, 'automatic_payment_failed', 'Automatic payment needs attention', 'A scheduled payment could not be completed. Review the provider status before trying again.', priority: 'urgent', context: ['currency' => $lockedSchedule->currency, 'amount' => (string) $lockedSchedule->amount]);
            }

            return $attempt->refresh();
        });
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
