<?php

namespace App\Domain\Charges;

use App\Domain\Enums\MoneyEntry;
use App\Models\FinancialProfile;
use App\Models\ObligationChargeTerm;
use Illuminate\Validation\ValidationException;

final class ChargeGuard
{
    public function assertTermsAllowed(
        FinancialProfile $profile,
        ?string $kind,
        ?string $basis,
        string $penaltyKind,
        bool $fixedTotalAgreedUpfront,
    ): void {
        if (! $profile->interest_free_mode) {
            return;
        }

        if ($kind === 'interest') {
            throw ValidationException::withMessages([
                'kind' => __('charges.guard.interest_blocked'),
            ]);
        }

        if ($penaltyKind === 'percent_of_overdue') {
            throw ValidationException::withMessages([
                'penalty_kind' => __('charges.guard.percentage_penalty_blocked'),
            ]);
        }

        if ($kind === 'profit' && $basis !== 'fixed_total') {
            throw ValidationException::withMessages([
                'basis' => __('charges.guard.non_fixed_profit_blocked'),
            ]);
        }

        if ($kind === 'profit' && ! $fixedTotalAgreedUpfront) {
            throw ValidationException::withMessages([
                'fixedTotalAgreedUpfront' => __('charges.fixed_total.agreed_upfront_required'),
            ]);
        }
    }

    public function assertManualEntryAllowed(
        FinancialProfile $profile,
        MoneyEntry $entry,
        ?ObligationChargeTerm $terms,
    ): void {
        if (! $profile->interest_free_mode) {
            return;
        }

        if ($entry === MoneyEntry::InterestCharge) {
            throw ValidationException::withMessages([
                'entry' => __('charges.guard.interest_blocked'),
            ]);
        }

        if ($entry === MoneyEntry::ProfitCharge
            && (! $terms instanceof ObligationChargeTerm
                || $terms->basis !== 'fixed_total'
                || $terms->fixed_total_agreed_upfront_at === null)) {
            throw ValidationException::withMessages([
                'entry' => __('charges.guard.non_fixed_profit_blocked'),
            ]);
        }
    }

    public function allowsFixedTotal(ObligationChargeTerm $terms): bool
    {
        return $terms->basis === 'fixed_total'
            && $terms->kind === 'profit'
            && $terms->total_payable_minor !== null;
    }
}
