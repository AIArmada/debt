<?php

namespace App\Domain\Calculations;

use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use App\Models\ObligationTerm;

class ObligationCalculator
{
    public function __construct(
        private readonly PawnStorageCalculator $pawnStorage,
        private readonly AdvancedObligationCalculator $advanced,
    ) {}

    /** @return array{label: string, estimate: int|null, explanation: string} */
    public function preview(Obligation $obligation, ?ObligationTerm $term): array
    {
        if ($obligation->isPositionReversed()) {
            return [
                'label' => 'Current position is reversed',
                'estimate' => null,
                'explanation' => 'The other party currently owes you. Charges are not projected while the signed ledger balance is below zero.',
            ];
        }

        if ($term === null || $term->calculation_method === 'none') {
            return [
                'label' => 'No calculation method',
                'estimate' => null,
                'explanation' => 'No additional charge is projected from the recorded terms.',
            ];
        }

        if ($term->calculation_method === 'fixed_installment') {
            $amount = $term->fixed_installment_amount ?? $obligation->minimum_payment_amount;

            return [
                'label' => 'Fixed instalment plan',
                'estimate' => null,
                'explanation' => $amount === null
                    ? 'A fixed instalment method is selected, but its amount has not been recorded yet.'
                    : 'Agreed instalment: '.MoneyAmount::format((int) $amount, (string) $obligation->currency).'. Use Schedule to set when reminders should run.',
            ];
        }

        if ($term->calculation_method === 'simple_interest' && $term->interest_rate !== null) {
            if ($obligation->current_total_balance === null) {
                return [
                    'label' => 'Calculation needs a balance',
                    'estimate' => null,
                    'explanation' => 'Enter a current balance before projecting a charge.',
                ];
            }

            $projection = $this->advanced->project($obligation, $term, 1, '0');
            $estimate = $projection['interest_total'] + $projection['late_fee_total'];

            return [
                'label' => 'Estimated monthly interest'.($projection['late_fee_total'] > 0 ? ' and late fees' : ''),
                'estimate' => $estimate,
                'explanation' => 'Based on the current balance, the recorded annual simple rate, and a 12-month year.',
            ];
        }

        if ($term->calculation_method === 'storage_fee' && $term->storage_fee_amount !== null) {
            $estimate = $this->pawnStorage->feeForPeriods((int) $term->storage_fee_amount, 1);

            return [
                'label' => 'Estimated storage fee per period',
                'estimate' => $estimate,
                'explanation' => 'Based on the recorded storage fee and period; actual redemption terms may differ.',
            ];
        }

        if (in_array($term->calculation_method, ['compound_interest', 'simple_interest'], true)) {
            $projection = $this->advanced->project($obligation, $term, 1, '0');
            $charge = $projection['interest_total'] + $projection['late_fee_total'];
            if ($charge > 0) {
                return [
                    'label' => $term->calculation_method === 'compound_interest' ? 'Estimated first-period compounded charge' : 'Estimated monthly interest and late fees',
                    'estimate' => $charge,
                    'explanation' => 'Based on the recorded rate, compounding period, due date, grace period, and the balance entered for this record.',
                ];
            }
        }

        return [
            'label' => 'Calculation needs more details',
            'estimate' => null,
            'explanation' => 'Add the required rate or fee details before projecting a charge.',
        ];
    }
}
