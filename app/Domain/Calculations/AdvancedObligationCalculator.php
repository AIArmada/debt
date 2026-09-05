<?php

namespace App\Domain\Calculations;

use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use App\Models\ObligationTerm;
use Carbon\CarbonImmutable;

class AdvancedObligationCalculator
{
    /**
     * Project a balance using the recorded terms.
     *
     * Monetary values are integer minor units. A string extra payment is kept
     * as a convenience for direct callers and is interpreted as major units;
     * an integer is already a minor-unit value from an application boundary.
     *
     * @return array{starting_balance: int, interest_total: int, late_fee_total: int, storage_fee_total: int, payments_total: int, ending_balance: int, currency: string, months_simulated: int, monthly_payment: int, schedule: list<array{month: int, opening: int, interest: int, late_fee: int, storage_fee: int, payment: int, ending: int}>}
     */
    public function project(Obligation $obligation, ?ObligationTerm $term, int $months = 12, string|int $extraPayment = '0', string $paymentFrequency = 'monthly'): array
    {
        $months = max(1, min(60, $months));
        $currency = (string) $obligation->currency;
        $startingBalance = (int) ($obligation->current_total_balance ?? 0);
        $balance = $startingBalance;
        $interestTotal = 0;
        $lateFeeTotal = 0;
        $storageFeeTotal = 0;
        $paymentsTotal = 0;
        $schedule = [];
        $lateFeeApplied = false;
        $extraPaymentMinor = is_int($extraPayment)
            ? $extraPayment
            : MoneyAmount::fromMajorOrZero($extraPayment, $currency);
        $basePayment = $term === null
            ? $obligation->minimum_payment_amount ?? 0
            : $term->fixed_installment_amount ?? $obligation->minimum_payment_amount ?? 0;
        $monthlyPayment = max(0, (int) $basePayment + $extraPaymentMinor);

        for ($month = 1; $month <= $months; $month++) {
            if ($balance <= 0) {
                break;
            }

            $opening = $balance;
            $interest = 0;
            $lateFee = 0;
            $storageFee = 0;

            if ($term !== null) {
                $interest = $this->monthlyInterest($opening, $startingBalance, $term);
                $storageFee = $this->monthlyStorageFee($term);

                if ($this->isLate($obligation, $term, CarbonImmutable::today()->addMonths($month - 1)) && (! $lateFeeApplied || (bool) data_get($term->formula, 'late_fee_recurring', false))) {
                    $lateFee = $this->lateFee($opening, $term);
                    $lateFeeApplied = true;
                }
            }

            $balance += $interest + $lateFee + $storageFee;
            $payment = min($balance, $this->paymentForMonth($monthlyPayment, $paymentFrequency));
            $balance -= $payment;

            $interestTotal += $interest;
            $lateFeeTotal += $lateFee;
            $storageFeeTotal += $storageFee;
            $paymentsTotal += $payment;
            $schedule[] = [
                'month' => $month,
                'opening' => $opening,
                'interest' => $interest,
                'late_fee' => $lateFee,
                'storage_fee' => $storageFee,
                'payment' => $payment,
                'ending' => $balance,
            ];
        }

        return [
            'starting_balance' => $startingBalance,
            'interest_total' => $interestTotal,
            'late_fee_total' => $lateFeeTotal,
            'storage_fee_total' => $storageFeeTotal,
            'payments_total' => $paymentsTotal,
            'ending_balance' => $balance,
            'currency' => $currency,
            'months_simulated' => count($schedule),
            'monthly_payment' => $monthlyPayment,
            'schedule' => $schedule,
        ];
    }

    private function monthlyInterest(int $balance, int $startingBalance, ObligationTerm $term): int
    {
        if (! in_array($term->calculation_method, ['simple_interest', 'compound_interest'], true) || $term->interest_rate === null) {
            return 0;
        }

        $rate = $this->precise(bcdiv((string) $term->interest_rate, '100', 12));
        $monthlyRate = match ($term->interest_period) {
            'monthly' => $rate,
            'weekly' => $this->precise(bcmul($rate, '4.3333333333', 12)),
            default => $this->precise(bcdiv($rate, '12', 12)),
        };
        $base = $term->calculation_method === 'compound_interest' || $term->compounding_period !== null
            ? $balance
            : $startingBalance;

        if ($term->calculation_method === 'compound_interest' || $term->compounding_period !== null) {
            $periods = match ($term->compounding_period) {
                'daily' => 30,
                'weekly' => 4,
                default => 1,
            };
            $periodRate = $this->precise(bcdiv($monthlyRate, (string) $periods, 12));
            $growth = $this->decimalPower($this->precise(bcadd('1', $periodRate, 12)), $periods);
            $growthDelta = $this->precise(bcsub($growth, '1', 12));
            $charge = $this->precise(bcmul((string) $base, $growthDelta, 12));

            return $this->roundMinor($charge);
        }

        return $this->roundMinor($this->precise(bcmul((string) $base, $monthlyRate, 12)));
    }

    private function monthlyStorageFee(ObligationTerm $term): int
    {
        if ($term->calculation_method !== 'storage_fee' || $term->storage_fee_amount === null) {
            return 0;
        }

        return match ($term->storage_fee_period) {
            'weekly' => $this->roundMinor($this->precise(bcmul((string) $term->storage_fee_amount, '4.3333333333', 12))),
            'daily' => $this->roundMinor($this->precise(bcmul((string) $term->storage_fee_amount, '30.4375', 12))),
            default => (int) $term->storage_fee_amount,
        };
    }

    private function lateFee(int $balance, ObligationTerm $term): int
    {
        $fixed = $term->late_fee_amount === null ? 0 : (int) $term->late_fee_amount;
        $rate = $term->late_fee_rate === null
            ? 0
            : $this->roundMinor($this->precise(bcmul((string) $balance, $this->precise(bcdiv((string) $term->late_fee_rate, '100', 12)), 12)));

        return $fixed + $rate;
    }

    private function isLate(Obligation $obligation, ObligationTerm $term, CarbonImmutable $date): bool
    {
        $due = $obligation->due_on ?? $obligation->next_due_on;

        if ($due === null) {
            return false;
        }

        $graceDays = max(0, (int) data_get($term->formula, 'grace_period_days', 0));

        return $date->isAfter(CarbonImmutable::parse((string) $due)->addDays($graceDays));
    }

    private function paymentForMonth(int $monthlyPayment, string $frequency): int
    {
        return match ($frequency) {
            'weekly' => $this->roundMinor($this->precise(bcmul((string) $monthlyPayment, '4.3333333333', 12))),
            'quarterly' => $this->roundMinor($this->precise(bcdiv((string) $monthlyPayment, '3', 12))),
            default => $monthlyPayment,
        };
    }

    /**
     * @param  numeric-string  $base
     * @return numeric-string
     */
    private function decimalPower(string $base, int $exponent): string
    {
        $result = '1.0000000000';
        for ($index = 0; $index < $exponent; $index++) {
            $result = $this->precise(bcmul($result, $base, 12));
        }

        return $result;
    }

    /** @param numeric-string $value */
    private function roundMinor(string $value): int
    {
        if (bccomp($value, '0', 12) < 0) {
            return (int) bcsub($value, '0.5', 0);
        }

        return (int) bcadd($value, '0.5', 0);
    }

    /** @return numeric-string */
    private function precise(string $value): string
    {
        if (! is_numeric($value)) {
            throw new \UnexpectedValueException('The calculation produced a non-numeric result.');
        }

        return $value;
    }
}
