<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Domain\Planning\RepaymentPlanContinuity;
use App\Models\CollectionSchedule;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use App\Services\CollectionScheduleSafety;
use App\Services\NativeCurrencyLedger;
use App\Services\PaymentScheduleSafety;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateTransaction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NativeCurrencyLedger $nativeCurrencyLedger,
        private readonly ActivityNotifier $activityNotifier,
        private readonly PaymentScheduleSafety $paymentScheduleSafety,
        private readonly CollectionScheduleSafety $collectionScheduleSafety,
        private readonly RepaymentPlanContinuity $repaymentPlanContinuity,
    ) {}

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
     *     collection_schedule_id?: string|null
     * } $data
     */
    public function handle(Obligation $obligation, FinancialTransaction $transaction, array $data): FinancialTransaction
    {
        return DB::transaction(function () use ($obligation, $transaction, $data): FinancialTransaction {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('recordTransaction', $lockedObligation);

            if ($lockedObligation->obligation_kind !== 'money') {
                throw ValidationException::withMessages(['obligation' => 'Only money obligations have financial transactions.']);
            }

            /** @var Collection<int, FinancialTransaction> $transactions */
            $transactions = FinancialTransaction::query()
                ->where('obligation_id', $lockedObligation->getKey())
                ->lockForUpdate()
                ->get();
            $lockedTransaction = $transactions->firstWhere('id', $transaction->getKey());

            if (! $lockedTransaction instanceof FinancialTransaction) {
                abort(404);
            }

            $promoteSnapshotToLedger = $lockedObligation->tracking_mode === 'snapshot';

            $before = $lockedTransaction->only([
                'status', 'amount', 'currency', 'entry_type', 'balance_effect',
                'balance_before', 'balance_after', 'occurred_on', 'external_reference', 'note',
            ]);
            $previousPosition = $lockedObligation->currentPositionDirection();
            $openingPrincipal = $this->nativeCurrencyLedger->openingPrincipal($lockedObligation, $transactions);

            $currency = strtoupper($data['currency']);
            $collectionScheduleId = $lockedTransaction->collection_schedule_id;
            if (! Currency::isSupported($currency)) {
                throw ValidationException::withMessages([
                    'currency' => 'Choose a supported currency. Each movement keeps its original currency; it does not need to match the record currency.',
                ]);
            }

            $positionDirection = $this->directionWithoutTransaction($lockedObligation, $lockedTransaction, $currency);
            $existingEntryType = strtoupper((string) $lockedTransaction->currency) === $currency
                ? $lockedTransaction->entry_type
                : null;
            $entryType = $this->entryType($lockedObligation, $data['entry_type'] ?? null, $currency, $existingEntryType, $positionDirection);
            $balanceEffect = $this->balanceEffect($lockedObligation, $entryType, $data['balance_effect'] ?? null, $currency, $positionDirection);

            if ($entryType !== 'collection') {
                $collectionScheduleId = null;
            } elseif ($collectionScheduleId !== null) {
                $schedule = CollectionSchedule::query()->whereKey($collectionScheduleId)->where('obligation_id', $lockedObligation->getKey())->first();
                if ($schedule === null || strtoupper((string) $schedule->currency) !== $currency) {
                    throw ValidationException::withMessages(['currency' => 'The collection currency must match its collection schedule.']);
                }
            }

            $amount = $this->normaliseAmount($data['amount'], $currency, $data['amount_minor'] ?? null);
            $status = $data['status'];
            $linkedAllocation = $lockedTransaction->repaymentPlanAllocation()->with('plan.budgetPeriod')->first();

            if ($linkedAllocation !== null) {
                $allocationCurrency = strtoupper((string) ($linkedAllocation->currency ?: $linkedAllocation->plan?->currency));
                $period = $linkedAllocation->plan?->budgetPeriod;

                if ($entryType !== 'payment' || $allocationCurrency !== $currency || $period === null || $data['occurred_on'] === null) {
                    throw ValidationException::withMessages(['repayment_plan_allocation_id' => 'A planned payment must stay a payment in its plan currency and budget period.']);
                }

                try {
                    $occurredDate = CarbonImmutable::parse($data['occurred_on'])->toDateString();
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['occurred_on' => 'Enter a valid payment date.']);
                }

                if ($occurredDate < CarbonImmutable::parse((string) $period->starts_on)->toDateString() || $occurredDate > CarbonImmutable::parse((string) $period->ends_on)->toDateString()) {
                    throw ValidationException::withMessages(['occurred_on' => 'The payment date must stay inside the plan budget period.']);
                }
            }

            $lockedTransaction->fill([
                'collection_schedule_id' => $collectionScheduleId,
                'entry_type' => $entryType,
                'balance_effect' => $balanceEffect,
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'occurred_on' => $data['occurred_on'],
                'submitted_at' => in_array($status, ['submitted', 'confirmed'], true) ? ($lockedTransaction->submitted_at ?? now()) : null,
                'confirmed_at' => $status === 'confirmed' ? ($lockedTransaction->confirmed_at ?? now()) : null,
                'external_reference' => $data['external_reference'],
                'note' => $data['note'],
                'balance_before' => null,
                'balance_after' => null,
                'principal_amount' => null,
                'interest_amount' => null,
                'fee_amount' => null,
            ]);
            $this->setComponentAmount($lockedTransaction, $entryType, $amount, $status);
            $lockedTransaction->save();

            $recalculated = $this->nativeCurrencyLedger->recalculate(
                $lockedObligation,
                FinancialTransaction::query()->where('obligation_id', $lockedObligation->getKey())->get(),
                $openingPrincipal,
            );

            $this->repaymentPlanContinuity->reconcile($lockedObligation, $lockedTransaction->fresh(), $before);

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
                    metadata: ['triggered_by_transaction_id' => $lockedTransaction->getKey()],
                );
            }

            $after = $lockedTransaction->fresh()->only([
                'status', 'amount', 'currency', 'entry_type', 'balance_effect',
                'balance_before', 'balance_after', 'occurred_on', 'external_reference', 'note',
            ]);

            $this->auditLogger->record(
                $lockedObligation->record->profile,
                null,
                FinancialTransaction::class,
                $lockedTransaction->getKey(),
                'updated',
                before: $before,
                after: $after,
                metadata: [
                    'obligation_balance' => $lockedObligation->current_total_balance,
                    'recalculated_transaction_ids' => $recalculated,
                ],
            );

            $currentPosition = $lockedObligation->currentPositionDirection();
            if ($currentPosition !== $previousPosition) {
                $this->paymentScheduleSafety->pauseWhenNoLongerPayable($lockedObligation);
                $this->collectionScheduleSafety->pauseWhenNoLongerReceivable($lockedObligation);
            }
            $this->activityNotifier->notifyObligation(
                $lockedObligation,
                $currentPosition !== $previousPosition ? 'position_changed' : 'movement_updated',
                $currentPosition !== $previousPosition ? 'A record position changed' : 'A record movement was updated',
                $currentPosition !== $previousPosition
                    ? 'A correction changed the current position of one of your private records.'
                    : 'A movement was corrected in one of your private records.',
                $lockedObligation->isPositionReversed() ? 'urgent' : 'normal',
                [
                    'currency' => $currency,
                    'amount' => $amount,
                    'status' => $status,
                    'position_direction' => $currentPosition,
                    'has_multiple_currency_exposures' => $lockedObligation->hasMultipleCurrencyExposures(),
                ],
            );

            return $lockedTransaction->fresh();
        });
    }

    private function setComponentAmount(FinancialTransaction $transaction, string $entryType, int $amount, string $status): void
    {
        if ($status !== 'confirmed') {
            return;
        }

        $attribute = match (true) {
            $entryType === 'advance' => 'principal_amount',
            $entryType === 'interest' => 'interest_amount',
            $entryType === 'fee' => 'fee_amount',
            in_array($entryType, ['payment', 'collection'], true) => 'principal_amount',
            default => null,
        };

        if ($attribute !== null) {
            $transaction->setAttribute($attribute, $amount);
        }
    }

    private function entryType(Obligation $obligation, ?string $entryType, string $currency, ?string $existingEntryType = null, ?string $positionDirection = null): string
    {
        $entryType ??= (($positionDirection ?? $obligation->direction) === 'payable' ? 'payment' : 'collection');

        if (! in_array($entryType, ['payment', 'collection', 'advance', 'interest', 'fee', 'adjustment', 'write_off', 'opening_balance'], true)) {
            throw ValidationException::withMessages(['entry_type' => 'Choose a valid ledger entry type.']);
        }

        $expectedDirection = $positionDirection
            ?? (in_array($existingEntryType, ['payment', 'collection'], true)
                ? ($existingEntryType === 'payment' ? 'payable' : 'receivable')
                : $obligation->direction);

        if ($entryType === 'payment' && $expectedDirection !== 'payable') {
            throw ValidationException::withMessages(['entry_type' => 'This currency currently shows money coming back to you. Record it as a collection.']);
        }

        if ($entryType === 'collection' && $expectedDirection !== 'receivable') {
            throw ValidationException::withMessages(['entry_type' => 'This currency currently shows money you need to pay. Record it as a payment.']);
        }

        return $entryType;
    }

    private function balanceEffect(Obligation $obligation, string $entryType, ?string $balanceEffect, string $currency, ?string $positionDirection = null): string
    {
        if ($entryType === 'adjustment') {
            if (! in_array($balanceEffect, ['increase', 'decrease'], true)) {
                throw ValidationException::withMessages(['balance_effect' => 'Choose whether this adjustment increases or decreases the balance.']);
            }

            return $balanceEffect;
        }

        if (in_array($entryType, ['payment', 'collection'], true)) {
            return ($positionDirection ?? $obligation->direction) === $obligation->direction
                ? 'decrease'
                : 'increase';
        }

        return in_array($entryType, ['advance', 'interest', 'fee', 'opening_balance'], true) ? 'increase' : 'decrease';
    }

    private function directionWithoutTransaction(Obligation $obligation, FinancialTransaction $transaction, string $currency): ?string
    {
        $balances = $obligation->currencyBalances();
        $transactionCurrency = strtoupper((string) $transaction->currency);

        if ($transaction->status === 'confirmed') {
            $amount = (int) $transaction->amount;
            $balances[$transactionCurrency] = ($balances[$transactionCurrency] ?? 0)
                + ($transaction->balance_effect === 'increase' ? -$amount : $amount);
        }

        $balance = $balances[strtoupper($currency)] ?? 0;
        if ($balance === 0) {
            return null;
        }

        return $balance > 0
            ? $obligation->direction
            : ($obligation->direction === 'payable' ? 'receivable' : 'payable');
    }

    private function normaliseAmount(string $amount, string $currency, ?int $amountInMinorUnits = null): int
    {
        try {
            $minor = $amountInMinorUnits === null
                ? MoneyAmount::fromMajor($amount, $currency)
                : MoneyAmount::fromMinor($amountInMinorUnits);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        if ($minor === null || $minor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        }

        return $minor;
    }
}
