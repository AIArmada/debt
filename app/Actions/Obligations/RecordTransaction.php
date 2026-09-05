<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Domain\Planning\RepaymentPlanContinuity;
use App\Models\CollectionSchedule;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Models\RepaymentPlanAllocation;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use App\Services\CollectionScheduleSafety;
use App\Services\NativeCurrencyLedger;
use App\Services\PaymentScheduleSafety;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type TransactionData array{
 *     status: string,
 *     amount: string,
 *     currency: string,
 *     occurred_on: string|null,
 *     external_reference: string|null,
 *     note: string|null,
 *     entry_type?: string|null,
 *     balance_effect?: string|null,
 *     amount_minor?: int|null,
 *     repayment_plan_allocation_id?: string|null,
 *     collection_schedule_id?: string|null
 * }
 */
class RecordTransaction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NativeCurrencyLedger $nativeCurrencyLedger,
        private readonly ActivityNotifier $activityNotifier,
        private readonly PaymentScheduleSafety $paymentScheduleSafety,
        private readonly CollectionScheduleSafety $collectionScheduleSafety,
        private readonly RepaymentPlanContinuity $repaymentPlanContinuity,
    ) {}

    /** @param TransactionData $data */
    public function handle(Obligation $obligation, array $data): FinancialTransaction
    {
        return $this->record($obligation, $data, true);
    }

    /**
     * Used by an authorised server-side payment provider execution.
     *
     * @param  TransactionData  $data
     */
    public function handleSystem(Obligation $obligation, array $data): FinancialTransaction
    {
        return $this->record($obligation, $data, false);
    }

    /**
     * @param array{
     *     status: string,
     *     amount: string,
     *     currency: string,
     *     occurred_on: string|null,
     *     external_reference: string|null,
     *     note: string|null,
     *     entry_type?: string,
     *     balance_effect?: string|null,
     *     amount_minor?: int|null,
     *     repayment_plan_allocation_id?: string|null,
     *     collection_schedule_id?: string|null
     * } $data
     */
    private function record(Obligation $obligation, array $data, bool $authorise): FinancialTransaction
    {
        return DB::transaction(function () use ($obligation, $data, $authorise): FinancialTransaction {
            $lockedObligation = Obligation::query()
                ->with('record.profile')
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($authorise) {
                Gate::authorize('recordTransaction', $lockedObligation);
            }

            if ($lockedObligation->obligation_kind !== 'money') {
                throw ValidationException::withMessages(['obligation' => 'Only money obligations have financial transactions.']);
            }

            // Only a confirmed movement starts detailed ledger tracking. Drafts,
            // submissions, failures, and cancellations must not promote a snapshot.
            $promoteSnapshotToLedger = $lockedObligation->tracking_mode === 'snapshot' && $data['status'] === 'confirmed';

            $currency = strtoupper($data['currency']);
            if (! Currency::isSupported($currency)) {
                throw ValidationException::withMessages([
                    'currency' => 'Choose a supported currency. Each movement keeps its original currency; it does not need to match the record currency.',
                ]);
            }

            $entryType = $this->entryType($lockedObligation, $data['entry_type'] ?? null, $currency);
            $balanceEffect = $this->balanceEffect($lockedObligation, $entryType, $data['balance_effect'] ?? null, $currency);

            $amount = $this->normaliseAmount($data['amount'], $currency, $data['amount_minor'] ?? null);
            $status = $data['status'];
            $allocation = $this->resolvePlanAllocation(
                $lockedObligation,
                $data['repayment_plan_allocation_id'] ?? null,
                $currency,
                $entryType,
                $data['occurred_on'] ?? null,
                $status,
            );
            $collectionSchedule = $this->resolveCollectionSchedule($lockedObligation, $data['collection_schedule_id'] ?? null, $currency, $entryType);

            $previousPosition = $lockedObligation->currentPositionDirection();

            $now = now();
            $attributes = [
                'obligation_id' => $lockedObligation->getKey(),
                'repayment_plan_allocation_id' => $allocation?->getKey(),
                'collection_schedule_id' => $collectionSchedule?->getKey(),
                'entry_type' => $entryType,
                'balance_effect' => $balanceEffect,
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'occurred_on' => $data['occurred_on'],
                'submitted_at' => in_array($status, ['submitted', 'confirmed'], true) ? $now : null,
                'confirmed_at' => $status === 'confirmed' ? $now : null,
                'external_reference' => $data['external_reference'],
                'note' => $data['note'],
            ];

            if ($status === 'confirmed') {
                $balance = $this->nativeCurrencyLedger->record($lockedObligation, $amount, $currency, $entryType, $balanceEffect);
                $attributes['balance_before'] = $balance['before'];
                $attributes['balance_after'] = $balance['after'];
                $this->setComponentAmount($attributes, $entryType, $amount);
            }

            $transaction = FinancialTransaction::create($attributes);

            $this->repaymentPlanContinuity->reconcile($lockedObligation, $transaction);

            if ($promoteSnapshotToLedger) {
                $lockedObligation->forceFill(['tracking_mode' => 'ledger'])->save();

                $this->auditLogger->record(
                    $lockedObligation->record->profile,
                    null,
                    Obligation::class,
                    $lockedObligation->getKey(),
                    'tracking_mode_promoted',
                    before: ['tracking_mode' => 'snapshot'],
                    after: ['tracking_mode' => 'ledger'],
                    metadata: ['triggered_by_transaction_id' => $transaction->getKey()],
                );
            }

            $this->auditLogger->record(
                $lockedObligation->record->profile,
                null,
                FinancialTransaction::class,
                $transaction->getKey(),
                'created',
                after: $transaction->only(['obligation_id', 'entry_type', 'balance_effect', 'status', 'amount', 'currency', 'occurred_on', 'balance_before', 'balance_after']),
                metadata: ['obligation_balance' => $lockedObligation->current_total_balance],
            );

            $currentPosition = $lockedObligation->currentPositionDirection();
            $positionChanged = $currentPosition !== $previousPosition;
            if ($positionChanged) {
                $this->paymentScheduleSafety->pauseWhenNoLongerPayable($lockedObligation);
                $this->collectionScheduleSafety->pauseWhenNoLongerReceivable($lockedObligation);
            }
            $this->activityNotifier->notifyObligation(
                $lockedObligation,
                $positionChanged ? 'position_changed' : 'movement_recorded',
                $positionChanged ? 'A record position changed' : 'A record has a new movement',
                $positionChanged
                    ? 'A confirmed movement changed the current position of one of your private records.'
                    : 'A movement was added to one of your private records.',
                $lockedObligation->isPositionReversed() ? 'urgent' : 'normal',
                [
                    'currency' => $currency,
                    'amount' => $amount,
                    'status' => $status,
                    'position_direction' => $currentPosition,
                    'has_multiple_currency_exposures' => $lockedObligation->hasMultipleCurrencyExposures(),
                ],
            );

            return $transaction;
        });
    }

    private function resolvePlanAllocation(
        Obligation $obligation,
        ?string $allocationId,
        string $currency,
        string $entryType,
        ?string $occurredOn,
        ?string $status,
    ): ?RepaymentPlanAllocation {
        if ($allocationId !== null) {
            $allocation = RepaymentPlanAllocation::query()
                ->with('plan.budgetPeriod')
                ->whereKey($allocationId)
                ->where('obligation_id', $obligation->getKey())
                ->first();

            if ($allocation === null) {
                throw ValidationException::withMessages(['repayment_plan_allocation_id' => 'Choose a valid plan allocation for this obligation.']);
            }

            $this->validatePlanAllocation($allocation, $obligation, $currency, $entryType, $occurredOn);

            return $allocation;
        }

        if ($entryType !== 'payment' || in_array($status, ['failed', 'cancelled'], true) || $occurredOn === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($occurredOn)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        return RepaymentPlanAllocation::query()
            ->with('plan.budgetPeriod')
            ->where('obligation_id', $obligation->getKey())
            ->where(function ($query) use ($currency): void {
                $query->where('currency', $currency)->orWhereNull('currency');
            })
            ->whereHas('plan', fn ($query) => $query
                ->where('profile_id', $obligation->record->profile_id)
                ->where('status', 'active'))
            ->whereHas('plan.budgetPeriod', fn ($query) => $query
                ->where('starts_on', '<=', $date)
                ->where('ends_on', '>=', $date))
            ->latest('created_at')
            ->first();
    }

    private function resolveCollectionSchedule(Obligation $obligation, ?string $scheduleId, string $currency, string $entryType): ?CollectionSchedule
    {
        if ($scheduleId === null) {
            return null;
        }

        if ($entryType !== 'collection') {
            throw ValidationException::withMessages(['collection_schedule_id' => 'A collection schedule can only be attached to a collection movement.']);
        }

        $schedule = CollectionSchedule::query()
            ->whereKey($scheduleId)
            ->where('obligation_id', $obligation->getKey())
            ->first();

        if ($schedule === null) {
            throw ValidationException::withMessages(['collection_schedule_id' => 'Choose a collection schedule for this obligation.']);
        }

        if (strtoupper((string) $schedule->currency) !== $currency) {
            throw ValidationException::withMessages(['collection_schedule_id' => 'The collection currency must match the selected collection schedule.']);
        }

        return $schedule;
    }

    private function validatePlanAllocation(
        RepaymentPlanAllocation $allocation,
        Obligation $obligation,
        string $currency,
        string $entryType,
        ?string $occurredOn,
    ): void {
        $period = $allocation->plan?->budgetPeriod;
        $allocationCurrency = strtoupper((string) ($allocation->currency ?: $allocation->plan?->currency));

        if ($allocation->plan?->profile_id !== $obligation->record->profile_id || $allocationCurrency !== $currency || $entryType !== 'payment') {
            throw ValidationException::withMessages(['repayment_plan_allocation_id' => 'This payment does not match the selected repayment plan.']);
        }

        if ($period === null || $occurredOn === null) {
            throw ValidationException::withMessages(['occurred_on' => 'A planned payment needs a date inside the budget period.']);
        }

        try {
            $date = CarbonImmutable::parse($occurredOn)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['occurred_on' => 'Enter a valid payment date.']);
        }

        if ($date < CarbonImmutable::parse((string) $period->starts_on)->toDateString() || $date > CarbonImmutable::parse((string) $period->ends_on)->toDateString()) {
            throw ValidationException::withMessages(['occurred_on' => 'The payment date must be inside the plan budget period.']);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function setComponentAmount(array &$attributes, string $entryType, int $amount): void
    {
        if ($entryType === 'advance') {
            $attributes['principal_amount'] = $amount;
        } elseif ($entryType === 'interest') {
            $attributes['interest_amount'] = $amount;
        } elseif ($entryType === 'fee') {
            $attributes['fee_amount'] = $amount;
        } elseif (in_array($entryType, ['payment', 'collection'], true)) {
            $attributes['principal_amount'] = $amount;
        }
    }

    private function entryType(Obligation $obligation, ?string $entryType, string $currency): string
    {
        $entryType ??= (($obligation->currencyPosition($currency)['direction'] ?? $obligation->direction) === 'payable' ? 'payment' : 'collection');

        if (! in_array($entryType, ['payment', 'collection', 'advance', 'interest', 'fee', 'adjustment', 'write_off', 'opening_balance'], true)) {
            throw ValidationException::withMessages([
                'entry_type' => 'Choose a valid ledger entry type.',
            ]);
        }

        $positionDirection = $obligation->currencyPosition($currency)['direction'];
        $expectedDirection = $positionDirection ?? $obligation->direction;

        if ($entryType === 'payment' && $expectedDirection !== 'payable') {
            throw ValidationException::withMessages([
                'entry_type' => 'This currency currently shows money coming back to you. Record it as a collection.',
            ]);
        }

        if ($entryType === 'collection' && $expectedDirection !== 'receivable') {
            throw ValidationException::withMessages([
                'entry_type' => 'This currency currently shows money you need to pay. Record it as a payment.',
            ]);
        }

        return $entryType;
    }

    private function balanceEffect(Obligation $obligation, string $entryType, ?string $balanceEffect, string $currency): string
    {
        if ($entryType === 'adjustment') {
            if (! in_array($balanceEffect, ['increase', 'decrease'], true)) {
                throw ValidationException::withMessages([
                    'balance_effect' => 'Choose whether this adjustment increases or decreases the balance.',
                ]);
            }

            return $balanceEffect;
        }

        if (in_array($entryType, ['payment', 'collection'], true)) {
            return ($obligation->currencyPosition($currency)['direction'] ?? $obligation->direction) === $obligation->direction
                ? 'decrease'
                : 'increase';
        }

        return in_array($entryType, ['advance', 'interest', 'fee', 'opening_balance'], true) ? 'increase' : 'decrease';
    }

    private function normaliseAmount(string $amount, string $currency, ?int $amountInMinorUnits = null): int
    {
        try {
            $minor = $amountInMinorUnits === null
                ? MoneyAmount::fromMajor($amount, $currency)
                : MoneyAmount::fromMinor($amountInMinorUnits);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception->getMessage(),
            ]);
        }

        if ($minor === null || $minor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        }

        return $minor;
    }
}
