<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use App\Models\PaymentSchedule;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePaymentSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{mode: string, amount: string, currency: string, frequency: string, starts_on: string, next_runs_on: string, per_payment_limit: string|null} $data */
    public function handle(Obligation $obligation, array $data): PaymentSchedule
    {
        Gate::authorize('manageSchedule', $obligation);

        if ($obligation->currentPositionDirection() !== 'payable') {
            throw ValidationException::withMessages(['amount' => 'Payment schedules are available only while the current position is something you need to pay.']);
        }

        $currency = strtoupper($data['currency']);
        if (! Currency::isSupported($currency)) {
            throw ValidationException::withMessages(['currency' => 'Choose a supported currency.']);
        }

        try {
            $amount = MoneyAmount::fromMajor($data['amount'], $currency);
            $perPaymentLimit = MoneyAmount::fromMajor($data['per_payment_limit'], $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        $schedule = $obligation->paymentSchedules()->create([
            'mode' => $data['mode'],
            'status' => $data['mode'] === 'automatic' ? 'awaiting_authorisation' : 'active',
            'amount' => $amount,
            'currency' => $currency,
            'frequency' => $data['frequency'],
            'starts_on' => $data['starts_on'],
            'next_runs_on' => $data['next_runs_on'],
            'per_payment_limit' => $perPaymentLimit,
        ]);

        $this->auditLogger->record(
            $obligation->record->profile,
            null,
            PaymentSchedule::class,
            $schedule->getKey(),
            'created',
            after: $schedule->only(['obligation_id', 'mode', 'status', 'amount', 'currency', 'frequency', 'starts_on', 'next_runs_on', 'per_payment_limit']),
        );

        return $schedule;
    }
}
