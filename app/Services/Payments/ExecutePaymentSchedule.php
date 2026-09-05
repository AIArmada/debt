<?php

namespace App\Services\Payments;

use App\Actions\Obligations\RecordTransaction;
use App\Models\PaymentExecutionAttempt;
use App\Models\PaymentSchedule;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;

class ExecutePaymentSchedule
{
    public function __construct(private readonly PaymentProviderManager $providers, private readonly RecordTransaction $recordTransaction, private readonly AuditLogger $auditLogger, private readonly ActivityNotifier $activityNotifier) {}

    public function handle(PaymentSchedule $schedule): PaymentExecutionAttempt
    {
        if ($schedule->next_runs_on !== null && $schedule->next_runs_on->isFuture()) {
            return $schedule->executionAttempts()->latest()->firstOrFail();
        }

        $schedule->load(['obligation.record.profile', 'authorisations']);
        if ($schedule->obligation->currentPositionDirection() !== 'payable') {
            $idempotencyKey = $schedule->getKey().':'.($schedule->next_runs_on?->toDateString() ?? today()->toDateString());
            $attempt = PaymentExecutionAttempt::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], ['payment_schedule_id' => $schedule->getKey(), 'provider' => 'none', 'amount' => $schedule->amount, 'currency' => $schedule->currency, 'status' => 'blocked_position_changed', 'attempted_at' => now()]);
            if ($attempt->wasRecentlyCreated) {
                $this->activityNotifier->notifyObligation($schedule->obligation, 'payment_blocked_position_changed', 'Payment schedule paused for review', 'A scheduled payment was blocked because the current position no longer requires a payment.', priority: 'urgent', context: ['currency' => $schedule->currency, 'amount' => (string) $schedule->amount]);
            }

            return $attempt;
        }
        $authorisation = $schedule->authorisations->first(fn ($item): bool => $item->status === 'approved' && $item->revoked_at === null);
        $idempotencyKey = $schedule->getKey().':'.($schedule->next_runs_on?->toDateString() ?? today()->toDateString());
        $attempt = PaymentExecutionAttempt::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], ['payment_schedule_id' => $schedule->getKey(), 'payment_authorisation_id' => $authorisation?->getKey(), 'provider' => $authorisation === null ? 'none' : $authorisation->provider, 'amount' => $schedule->amount, 'currency' => $schedule->currency, 'status' => $authorisation === null ? 'requires_approval' : 'pending', 'attempted_at' => now()]);
        if ($attempt->status !== 'pending') {
            if ($attempt->wasRecentlyCreated && $attempt->status === 'requires_approval') {
                $this->activityNotifier->notifyObligation($schedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment is waiting for your explicit approval before it can run.', priority: 'urgent', context: ['currency' => $schedule->currency, 'amount' => (string) $schedule->amount]);
            }

            return $attempt;
        }

        if ($authorisation === null || ($authorisation->max_amount !== null && (int) $schedule->amount > (int) $authorisation->max_amount)) {
            $updated = $attempt->updateQuietly(['status' => 'requires_approval']);
            if ($updated) {
                $this->activityNotifier->notifyObligation($schedule->obligation, 'payment_requires_approval', 'Payment approval needed', 'A scheduled payment needs approval because its authorisation is missing or its limit is too low.', priority: 'urgent', context: ['currency' => $schedule->currency, 'amount' => (string) $schedule->amount]);
            }

            return $updated ? $attempt->refresh() : $attempt;
        }

        try {
            $result = $this->providers->charge($schedule, $authorisation, $idempotencyKey);
            $transaction = $this->recordTransaction->handleSystem($schedule->obligation, ['status' => 'confirmed', 'amount' => '0', 'amount_minor' => (int) $schedule->amount, 'currency' => $schedule->currency, 'occurred_on' => today()->toDateString(), 'external_reference' => $result['external_reference'], 'note' => 'Automatic payment through '.$authorisation->provider.' provider.']);
            $attempt->update(['financial_transaction_id' => $transaction->getKey(), 'status' => 'succeeded', 'external_reference' => $result['external_reference'], 'response_payload' => $result['payload'], 'completed_at' => now()]);
            $schedule->update(['next_runs_on' => $this->nextRun($schedule)]);
            $this->auditLogger->record($schedule->obligation->record->profile, null, PaymentExecutionAttempt::class, $attempt->getKey(), 'succeeded', after: $attempt->only(['status', 'external_reference', 'completed_at']));
        } catch (\Throwable $exception) {
            $attempt->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
            $this->activityNotifier->notifyObligation($schedule->obligation, 'automatic_payment_failed', 'Automatic payment needs attention', 'A scheduled payment could not be completed. Review the provider status before trying again.', priority: 'urgent', context: ['currency' => $schedule->currency, 'amount' => (string) $schedule->amount]);
        }

        return $attempt->refresh();
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
