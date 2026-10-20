<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;

final class ChargeTermsHash
{
    public function forObligation(Obligation $obligation): string
    {
        $obligation->loadMissing('moneySubject', 'chargeTerms');
        $terms = $obligation->chargeTerms;

        if (! $terms instanceof ObligationChargeTerm || $obligation->moneySubject === null) {
            throw new \InvalidArgumentException('Charge terms require a money obligation.');
        }

        $principalMinor = (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $obligation->moneySubject->currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
            ->sum('amount_minor');

        $canonical = [
            'allocation_order' => $terms->allocation_order,
            'auto_apply_on_acceptance' => $terms->auto_apply_on_acceptance,
            'basis' => $terms->basis,
            'cap_minor' => $terms->cap_minor,
            'currency' => $obligation->moneySubject->currency,
            'fixed_total_agreed_upfront' => $terms->fixed_total_agreed_upfront_at !== null,
            'fixed_total_agreed_upfront_by' => $terms->fixed_total_agreed_upfront_by,
            'grace_days' => $terms->grace_days,
            'is_formal' => $terms->is_formal,
            'kind' => $terms->kind,
            'mode' => $terms->auto_apply_on_acceptance && in_array($terms->mode, ['auto_propose', 'auto_apply'], true)
                ? 'auto_apply_on_acceptance'
                : $terms->mode,
            'penalty_kind' => $terms->penalty_kind,
            'penalty_value' => $terms->penalty_value,
            'period' => $terms->period,
            'principal_minor' => $principalMinor,
            'rate' => $terms->rate,
            'total_payable_minor' => $terms->total_payable_minor,
            'version' => 1,
        ];
        ksort($canonical);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
