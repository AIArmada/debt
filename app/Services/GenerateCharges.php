<?php

namespace App\Services;

use App\Domain\Charges\ChargeAnniversary;
use App\Domain\Charges\ChargeCalculator;
use App\Domain\Charges\ChargeGuard;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Movements\MovementConfirmation;
use App\Domain\Queries\BalanceBreakdown;
use App\Domain\Queries\ChargeTermsHash;
use App\Domain\Queries\OutstandingBalance;
use App\Models\FinancialProfile;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class GenerateCharges
{
    public function __construct(
        private readonly ChargeCalculator $calculator,
        private readonly ChargeGuard $chargeGuard,
        private readonly BalanceBreakdown $balanceBreakdown,
        private readonly ChargeTermsHash $chargeTermsHash,
        private readonly MovementConfirmation $movementConfirmation,
        private readonly OutstandingBalance $outstandingBalance,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{generated: int, skipped: int}
     */
    public function forProfile(FinancialProfile $profile, CarbonInterface $today): array
    {
        $generated = 0;
        $skipped = 0;
        $obligations = Obligation::query()
            ->where('subject_type', SubjectType::Money->value)
            ->whereHas('record', fn (Builder $query): Builder => $query->where('profile_id', $profile->getKey()))
            ->whereHas('chargeTerms')
            ->with(['record.profile', 'moneySubject', 'chargeTerms'])
            ->orderBy('id')
            ->get();

        foreach ($obligations as $obligation) {
            try {
                $generated += $this->forObligation($obligation, $today);
            } catch (ValidationException $exception) {
                $skipped++;
                Log::notice('Automatic charges were skipped by the profile guard.', [
                    'obligation_id' => $obligation->getKey(),
                    'errors' => $exception->errors(),
                ]);
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    public function forObligation(Obligation $obligation, CarbonInterface $today): int
    {
        return DB::transaction(function () use ($obligation, $today): int {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->with(['record.profile', 'moneySubject'])
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedObligation->subject_type !== SubjectType::Money
                || in_array($lockedObligation->status, [ObligationStatus::Paused, ObligationStatus::Disputed, ObligationStatus::Settled], true)) {
                return 0;
            }

            $terms = ObligationChargeTerm::query()
                ->whereKey($lockedObligation->getKey())
                ->lockForUpdate()
                ->first();
            if (! $terms instanceof ObligationChargeTerm || ! in_array($terms->mode, ['auto_propose', 'auto_apply'], true)) {
                return 0;
            }

            $lockedObligation->setRelation('chargeTerms', $terms);
            $profile = $lockedObligation->record->profile;
            $this->chargeGuard->assertTermsAllowed(
                $profile,
                $terms->kind,
                $terms->basis,
                $terms->penalty_kind,
                $terms->fixed_total_agreed_upfront_at !== null,
            );

            $currency = (string) $lockedObligation->moneySubject->currency;
            $termsHash = $this->chargeTermsHash->forObligation($lockedObligation);
            $termsAccepted = $terms->accepted_terms_hash !== null
                && hash_equals((string) $terms->accepted_terms_hash, $termsHash);
            $autoApplyAllowed = $termsAccepted || $terms->is_formal;
            if ($terms->mode === 'auto_apply' && ! $autoApplyAllowed) {
                return 0;
            }

            if ($terms->basis === 'fixed_total') {
                $generated = $this->generateFixedTotal(
                    $lockedObligation,
                    $terms,
                    $currency,
                    $termsHash,
                    $autoApplyAllowed,
                    $today,
                );
            } else {
                $generated = 0;
            }

            if ($this->isCompleteForAccrual($terms)) {
                $generated += $this->generateAccrualPeriods(
                    $lockedObligation,
                    $terms,
                    $currency,
                    $termsHash,
                    $today,
                    $autoApplyAllowed,
                );
            } elseif ($terms->basis !== 'fixed_total') {
                return $generated;
            }

            $generated += $this->generatePenalty(
                $lockedObligation,
                $terms,
                $currency,
                $termsHash,
                $today,
            );

            return $generated;
        });
    }

    private function generateFixedTotal(
        Obligation $obligation,
        ObligationChargeTerm $terms,
        string $currency,
        string $termsHash,
        bool $autoApplyAllowed,
        CarbonInterface $today,
    ): int {
        if (! $this->chargeGuard->allowsFixedTotal($terms)
            || $terms->fixed_total_posted_at !== null
            || $terms->total_payable_minor === null) {
            return 0;
        }

        $principalMinor = $this->principalTotal($obligation, $currency);
        $profitMinor = (int) $terms->total_payable_minor - $principalMinor;
        if ($profitMinor <= 0) {
            return 0;
        }

        $openingDate = $this->openingDate($obligation, $currency);
        if ($openingDate === null) {
            return 0;
        }

        $entry = MoneyEntry::ProfitCharge;
        $periodKey = $this->periodKey($obligation, $entry, $openingDate->toDateString());
        $movement = $this->movementOnce($obligation, $entry, $currency, $profitMinor, $today, $periodKey, [
            'basis' => 'fixed_total',
            'basis_amount_minor' => $principalMinor,
            'total_payable_minor' => (int) $terms->total_payable_minor,
            'terms_hash' => $termsHash,
            'fixed_total' => true,
            'window_start' => $openingDate,
            'window_end' => $today->toDateString(),
        ]);

        if (! $movement->wasRecentlyCreated && $movement->status === MovementStatus::Voided) {
            return 0;
        }

        $created = $movement->wasRecentlyCreated;
        if ($terms->mode === 'auto_apply' && $autoApplyAllowed && $movement->status === MovementStatus::Proposed) {
            $movement->setRelation('obligation', $obligation);
            $this->movementConfirmation->confirm($movement, null, 'charge_auto_applied');
            $terms->forceFill(['fixed_total_posted_at' => now()])->save();
        } elseif ($movement->status === MovementStatus::Confirmed
            && is_string($movement->calculation['terms_hash'] ?? null)
            && hash_equals($termsHash, (string) $movement->calculation['terms_hash'])) {
            $terms->forceFill(['fixed_total_posted_at' => now()])->save();
        } elseif ($created) {
            $this->recordProposal($obligation, $movement, $termsHash);
        }

        return $created ? 1 : 0;
    }

    private function generateAccrualPeriods(
        Obligation $obligation,
        ObligationChargeTerm $terms,
        string $currency,
        string $termsHash,
        CarbonInterface $today,
        bool $autoApplyAllowed,
    ): int {
        $openingDate = $this->openingDate($obligation, $currency);
        if ($openingDate === null) {
            return 0;
        }

        $anchor = $openingDate->addDays((int) $terms->grace_days);
        $start = $terms->last_charge_on === null
            ? $anchor
            : CarbonImmutable::parse($terms->last_charge_on->toDateString());
        $endOfToday = CarbonImmutable::parse($today->toDateString());
        $next = $this->nextPeriod($anchor, $start, (string) $terms->period);
        $principal = $this->balanceBreakdown->forCurrency($obligation, $currency);
        $originalPrincipal = $this->principalTotal($obligation, $currency);
        $alreadyCharged = $principal['charges_in'];
        $reservedProposed = $this->proposedChargesTotal($obligation, $currency);
        $generated = 0;
        $entry = $terms->kind === 'interest' ? MoneyEntry::InterestCharge : MoneyEntry::ProfitCharge;

        while (! $next->greaterThan($endOfToday)) {
            $calculation = $this->calculator->calculate(
                $terms,
                $currency,
                $start,
                $next,
                $originalPrincipal,
                $principal['unpaid_principal'],
                $alreadyCharged,
                $reservedProposed,
                $anchor,
            );
            if ($calculation['amount_minor'] <= 0) {
                break;
            }

            $periodKey = $this->periodKey($obligation, $entry, $start->toDateString());
            $movement = $this->movementOnce(
                $obligation,
                $entry,
                $currency,
                $calculation['amount_minor'],
                $next,
                $periodKey,
                $calculation['breakdown'] + ['terms_hash' => $termsHash],
            );
            $created = $movement->wasRecentlyCreated;
            $wasProposed = $movement->status === MovementStatus::Proposed;

            if ($terms->mode === 'auto_apply' && $autoApplyAllowed && $movement->status === MovementStatus::Proposed) {
                $movement->setRelation('obligation', $obligation);
                $this->movementConfirmation->confirm($movement, null, 'charge_auto_applied');
            } elseif ($created) {
                $this->recordProposal($obligation, $movement, $termsHash);
            }

            if ($wasProposed && $movement->status === MovementStatus::Confirmed) {
                if (! $created) {
                    $reservedProposed = max(0, $reservedProposed - (int) $movement->amount_minor);
                }
                $alreadyCharged += (int) $movement->amount_minor;
                $this->outstandingBalance->forget($obligation);
            } elseif ($created && $movement->status === MovementStatus::Proposed) {
                $reservedProposed += (int) $movement->amount_minor;
            }

            $generated += $created ? 1 : 0;
            $start = $next;
            $next = $this->nextPeriod($anchor, $start, (string) $terms->period);
            $terms->forceFill([
                'last_charge_on' => $start->toDateString(),
                'next_charge_on' => $next->toDateString(),
            ])->save();
        }

        return $generated;
    }

    private function generatePenalty(
        Obligation $obligation,
        ObligationChargeTerm $terms,
        string $currency,
        string $termsHash,
        CarbonInterface $today,
    ): int {
        if (! $obligation->due_on instanceof CarbonInterface || $terms->penalty_kind === 'none' || $terms->penalty_value === null) {
            return 0;
        }

        $penaltyStart = CarbonImmutable::parse($obligation->due_on->toDateString())
            ->addDays((int) $terms->grace_days + 1);
        $endOfToday = CarbonImmutable::parse($today->toDateString());
        if ($penaltyStart->greaterThan($endOfToday)) {
            return 0;
        }

        $amountMinor = (int) $terms->penalty_value;
        $calculation = [
            'penalty_kind' => $terms->penalty_kind,
            'penalty_value' => (int) $terms->penalty_value,
            'terms_hash' => $termsHash,
            'window_start' => $penaltyStart->toDateString(),
            'window_end' => $endOfToday->toDateString(),
        ];
        if ($terms->penalty_kind === 'percent_of_overdue') {
            $this->chargeGuard->assertTermsAllowed(
                $obligation->record->profile,
                $terms->kind,
                $terms->basis,
                $terms->penalty_kind,
                $terms->fixed_total_agreed_upfront_at !== null,
            );
            $balance = $this->outstandingBalance->forObligation($obligation)[$currency] ?? 0;
            $overdueMinor = abs($balance);
            $rawPenalty = bcdiv(bcmul((string) $overdueMinor, (string) $terms->penalty_value, 12), '10000', 12);
            $amountMinor = (int) bcadd($rawPenalty, '0.5', 0);
            $calculation['overdue_balance_minor'] = $overdueMinor;
            $calculation['basis_points'] = (int) $terms->penalty_value;
        }

        if ($amountMinor <= 0) {
            return 0;
        }

        $entry = MoneyEntry::Penalty;
        $periodKey = $this->periodKey($obligation, $entry, $penaltyStart->toDateString());
        $movement = $this->movementOnce(
            $obligation,
            $entry,
            $currency,
            $amountMinor,
            $endOfToday,
            $periodKey,
            $calculation,
        );
        if ($movement->wasRecentlyCreated) {
            $this->recordProposal($obligation, $movement, $termsHash);

            return 1;
        }

        return 0;
    }

    /** @param array<string, mixed> $calculation */
    private function movementOnce(
        Obligation $obligation,
        MoneyEntry $entry,
        string $currency,
        int $amountMinor,
        CarbonInterface $occurredOn,
        string $periodKey,
        array $calculation,
    ): MoneyMovement {
        return $obligation->moneyMovements()->firstOrCreate(
            ['period_key' => $periodKey],
            [
                'entry' => $entry,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'occurred_on' => $occurredOn->toDateString(),
                'status' => MovementStatus::Proposed,
                'note' => 'Automatically generated charge',
                'calculation' => $calculation,
                'recorded_by' => $obligation->record->profile->owner_user_id,
            ],
        );
    }

    private function recordProposal(Obligation $obligation, MoneyMovement $movement, string $termsHash): void
    {
        $this->activityLogger->record(
            $obligation->record->profile,
            null,
            $movement,
            'charge_proposed',
            after: [
                'entry' => $movement->entry->value,
                'amount_minor' => $movement->amount_minor,
                'currency' => $movement->currency,
                'terms_hash' => $termsHash,
                'period_key' => $movement->period_key,
                'calculation' => $movement->calculation,
            ],
        );
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

    private function proposedChargesTotal(Obligation $obligation, string $currency): int
    {
        return (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Proposed->value)
            ->whereIn('entry', [
                MoneyEntry::Charge->value,
                MoneyEntry::InterestCharge->value,
                MoneyEntry::ProfitCharge->value,
                MoneyEntry::Fee->value,
                MoneyEntry::Penalty->value,
            ])
            ->sum('amount_minor');
    }

    private function openingDate(Obligation $obligation, string $currency): ?CarbonImmutable
    {
        $date = MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
            ->orderBy('occurred_on')
            ->value('occurred_on');

        return $date === null ? null : CarbonImmutable::parse((string) $date);
    }

    private function isCompleteForAccrual(ObligationChargeTerm $terms): bool
    {
        $rate = $terms->rate === null ? null : (string) $terms->rate;

        return in_array($terms->kind, ['interest', 'profit'], true)
            && in_array($terms->basis, ['flat', 'reducing_simple'], true)
            && in_array($terms->period, ['daily', 'monthly', 'yearly'], true)
            && $rate !== null
            && is_numeric($rate)
            && bccomp($rate, '0', 8) > 0;
    }

    private function nextPeriod(CarbonImmutable $anchor, CarbonImmutable $date, string $period): CarbonImmutable
    {
        return ChargeAnniversary::nextAfter($anchor, $date, $period);
    }

    private function periodKey(Obligation $obligation, MoneyEntry $entry, string $periodStart): string
    {
        return $obligation->getKey().':'.$entry->value.':'.$periodStart;
    }
}
