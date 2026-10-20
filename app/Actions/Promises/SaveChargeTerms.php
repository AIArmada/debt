<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\SaveChargeTermsData;
use App\Domain\Charges\ChargeGuard;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\ChargeTermsHash;
use App\Domain\StringNormalizer;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveChargeTerms
{
    public function __construct(
        private readonly ChargeTermsHash $chargeTermsHash,
        private readonly ChargeGuard $chargeGuard,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Obligation $obligation, SaveChargeTermsData $data): ObligationChargeTerm
    {
        $obligation->loadMissing('record.profile', 'moneySubject');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data): ObligationChargeTerm {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->with(['record.profile', 'moneySubject'])
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            if ($lockedObligation->subject_type !== SubjectType::Money || $lockedObligation->moneySubject === null) {
                throw ValidationException::withMessages(['chargeTerms' => 'Charges can only be set for a money promise.']);
            }

            $currency = StringNormalizer::uppercaseTrimmed($lockedObligation->moneySubject->currency);
            if ($currency !== StringNormalizer::uppercaseTrimmed($data->currency)) {
                throw ValidationException::withMessages(['currency' => 'Charge terms must use the promise currency.']);
            }

            $term = ObligationChargeTerm::query()
                ->whereKey($lockedObligation->getKey())
                ->lockForUpdate()
                ->first();
            $isExisting = $term instanceof ObligationChargeTerm;
            $storedTermsHash = $isExisting ? $term->terms_hash : null;
            $term ??= new ObligationChargeTerm(['obligation_id' => $lockedObligation->getKey()]);
            $lockedObligation->setRelation('chargeTerms', $term);

            $oldHash = $isExisting ? $this->chargeTermsHash->forObligation($lockedObligation) : null;
            $storedHashIsStale = $isExisting && $term->terms_hash !== $oldHash;
            $previous = [
                'mode' => $term->mode,
                'kind' => $term->kind,
                'basis' => $term->basis,
                'period' => $term->period,
                'rate' => $term->rate,
                'total_payable_minor' => $term->total_payable_minor,
                'allocation_order' => $term->allocation_order,
            ];
            $previousTotal = $term->total_payable_minor === null ? null : (int) $term->total_payable_minor;
            $principalMinor = $this->principalTotal($lockedObligation, $currency);
            $upfrontTermsChanged = ! $isExisting
                || $storedHashIsStale
                || $previousTotal !== $data->totalPayableMinor;

            $term->forceFill([
                'mode' => $data->mode,
                'kind' => $data->kind,
                'rate' => $data->rate,
                'basis' => $data->basis,
                'period' => $data->period,
                'grace_days' => $data->graceDays,
                'cap_minor' => $data->capMinor,
                'total_payable_minor' => $data->totalPayableMinor,
                'penalty_kind' => $data->penaltyKind,
                'penalty_value' => $data->penaltyValue,
                'allocation_order' => $data->allocationOrder,
                'is_formal' => $data->isFormal,
                'auto_apply_on_acceptance' => $data->autoApplyOnAcceptance,
            ]);

            if ($data->basis !== 'fixed_total' || ! $data->fixedTotalAgreedUpfront) {
                $term->forceFill([
                    'fixed_total_agreed_upfront_at' => null,
                    'fixed_total_agreed_upfront_by' => null,
                ]);
            } elseif ($upfrontTermsChanged || $term->fixed_total_agreed_upfront_at === null) {
                $term->forceFill([
                    'fixed_total_agreed_upfront_at' => now(),
                    'fixed_total_agreed_upfront_by' => $user->getKey(),
                ]);
            }

            $this->validateCompleteness($data, $principalMinor);
            $this->chargeGuard->assertTermsAllowed(
                $lockedObligation->record->profile,
                $data->kind,
                $data->basis,
                $data->penaltyKind,
                $term->fixed_total_agreed_upfront_at !== null,
            );

            $lockedObligation->setRelation('chargeTerms', $term);
            $newHash = $this->chargeTermsHash->forObligation($lockedObligation);
            $hashChanged = $oldHash === null || $oldHash !== $newHash;
            $shouldDowngradeAutoApply = $isExisting
                ? $hashChanged && ($previous['mode'] === 'auto_apply' || ! $data->isFormal)
                : ! $data->isFormal;
            if ($data->mode === 'auto_apply' && $shouldDowngradeAutoApply) {
                $term->forceFill(['mode' => 'auto_propose']);
                $newHash = $this->chargeTermsHash->forObligation($lockedObligation);
                $hashChanged = $oldHash === null || $oldHash !== $newHash;
            }

            if ($hashChanged || $storedHashIsStale) {
                $term->forceFill([
                    'accepted_terms_hash' => null,
                    'accepted_at' => null,
                    'accepted_name' => null,
                ]);
            }

            if ($isExisting && $storedTermsHash !== $newHash) {
                $term->forceFill(['last_charge_on' => null, 'next_charge_on' => null]);
                $this->voidProposedChargeMovements($user, $lockedObligation);
            }

            $term->forceFill(['terms_hash' => $newHash])->save();

            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $term,
                'charge_terms_saved',
                before: $isExisting ? $previous : null,
                after: [
                    'mode' => $term->mode,
                    'kind' => $term->kind,
                    'basis' => $term->basis,
                    'period' => $term->period,
                    'rate' => $term->rate,
                    'total_payable_minor' => $term->total_payable_minor,
                    'allocation_order' => $term->allocation_order,
                    'terms_hash' => $newHash,
                ],
            );

            return $term->refresh();
        });
    }

    private function principalTotal(Obligation $obligation, string $currency): int
    {
        return (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
            ->sum('amount_minor');
    }

    private function voidProposedChargeMovements(User $user, Obligation $obligation): void
    {
        $chargeEntries = [
            MoneyEntry::Charge->value,
            MoneyEntry::InterestCharge->value,
            MoneyEntry::ProfitCharge->value,
            MoneyEntry::Fee->value,
            MoneyEntry::Penalty->value,
        ];

        $proposals = MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('status', MovementStatus::Proposed->value)
            ->whereIn('entry', $chargeEntries)
            ->lockForUpdate()
            ->get();

        foreach ($proposals as $proposal) {
            $periodKey = $proposal->period_key;
            $proposal->forceFill([
                'status' => MovementStatus::Voided,
                'void_reason' => 'Charge terms changed',
                'period_key' => null,
            ])->save();

            $this->activityLogger->record(
                $obligation->record->profile,
                $user,
                $proposal,
                'charge_proposal_invalidated',
                before: ['status' => MovementStatus::Proposed->value, 'period_key' => $periodKey],
                after: [
                    'status' => MovementStatus::Voided->value,
                    'period_key' => null,
                    'reason' => 'Charge terms changed',
                ],
            );
        }
    }

    private function validateCompleteness(SaveChargeTermsData $data, int $principalMinor): void
    {
        if (! in_array($data->mode, ['auto_propose', 'auto_apply'], true)) {
            return;
        }

        if ($data->kind === 'none' || $data->basis === null || $data->period === null) {
            throw ValidationException::withMessages(['chargeTerms' => 'Automatic charges need a kind, basis, and period.']);
        }

        if ($data->basis === 'fixed_total') {
            if ($data->kind !== 'profit' || $data->totalPayableMinor === null) {
                throw ValidationException::withMessages(['totalPayable' => 'Fixed total terms need profit and a total payable amount.']);
            }

            if ($data->totalPayableMinor <= $principalMinor) {
                throw ValidationException::withMessages(['totalPayable' => 'Total payable must be greater than the original principal.']);
            }
        } else {
            $rate = $data->rate;
            if ($rate === null || ! is_numeric($rate) || bccomp($rate, '0', 8) <= 0) {
                throw ValidationException::withMessages(['rate' => 'Automatic accrual needs a rate greater than zero.']);
            }
        }

        if ($data->penaltyKind !== 'none' && $data->penaltyValue === null) {
            throw ValidationException::withMessages(['penaltyValue' => 'Automatic penalties need a value.']);
        }
    }
}
