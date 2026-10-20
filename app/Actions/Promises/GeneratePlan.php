<?php

namespace App\Actions\Promises;

use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\PlanStrategy;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\RecordVisibilityQuery;
use App\Models\BudgetPeriod;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\RepaymentPlan;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final class GeneratePlan
{
    public function __construct(
        private readonly ProfileAccess $profileAccess,
        private readonly OutstandingBalance $outstandingBalance,
        private readonly ActivityLogger $activityLogger,
        private readonly RecordVisibilityQuery $recordVisibilityQuery,
    ) {}

    public function handle(User $user, BudgetPeriod $period, PlanStrategy $strategy = PlanStrategy::DueDate): RepaymentPlan
    {
        $period->loadMissing('profile');
        if (! $this->profileAccess->can($user, $period->profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $period, $strategy): RepaymentPlan {
            $period->repaymentPlans()->where('status', PlanStatus::Active->value)->update(['status' => PlanStatus::Superseded]);
            $plan = $period->repaymentPlans()->create([
                'status' => PlanStatus::Active,
                'strategy' => $strategy,
                'generated_at' => now(),
            ]);
            $capacity = $period->capacity();
            $obligations = $this->recordVisibilityQuery->forUser($period->profile->records()->getQuery(), $user)
                ->where('is_archived', false)
                ->with(['obligations.moneySubject', 'obligations.chargeTerms'])
                ->get()
                ->flatMap(static fn (Record $record): EloquentCollection => $record->obligations)
                ->filter(static fn (Obligation $obligation): bool => $obligation->subject_type === SubjectType::Money && $obligation->status === ObligationStatus::Open && $obligation->direction === Direction::Payable)
                ->map(function (Obligation $obligation) use ($period): array {
                    $balance = $this->outstandingBalance->forObligation($obligation)[$period->currency] ?? 0;

                    $balance = max(0, $balance);

                    return [
                        'obligation' => $obligation,
                        'balance' => $balance,
                        'cap' => $this->periodCap($obligation, $period, $balance),
                    ];
                })
                ->filter(static fn (array $item): bool => $item['balance'] > 0 && $item['cap'] > 0)
                ->sort(function (array $left, array $right) use ($strategy): int {
                    $comparison = match ($strategy) {
                        PlanStrategy::Snowball => $left['balance'] <=> $right['balance'],
                        PlanStrategy::Avalanche => $this->compareAnnualRates($right['obligation'], $left['obligation']),
                        PlanStrategy::DueDate => 0,
                    };
                    if ($comparison !== 0) {
                        return $comparison;
                    }

                    $leftDate = $left['obligation']->due_on?->getTimestamp() ?? PHP_INT_MAX;
                    $rightDate = $right['obligation']->due_on?->getTimestamp() ?? PHP_INT_MAX;

                    return $leftDate <=> $rightDate
                        ?: ($strategy === PlanStrategy::DueDate ? $right['balance'] <=> $left['balance'] : 0)
                        ?: $left['obligation']->getKey() <=> $right['obligation']->getKey();
                });

            foreach ($obligations as $item) {
                if ($capacity <= 0) {
                    break;
                }
                $planned = min($capacity, $item['balance'], $item['cap']);
                $plan->allocations()->create(['obligation_id' => $item['obligation']->getKey(), 'planned_minor' => $planned]);
                $capacity -= $planned;
            }
            $this->activityLogger->record($period->profile, $user, $plan, 'repayment_plan_generated', after: [
                'plan_id' => $plan->getKey(),
                'capacity_minor' => $period->capacity(),
                'strategy' => $strategy->value,
            ]);

            return $plan->load('allocations');
        });
    }

    private function periodCap(Obligation $obligation, BudgetPeriod $period, int $balance): int
    {
        $instalments = $obligation->instalments()->where('currency', $period->currency);
        if (! $instalments->exists()) {
            return $balance;
        }

        $arrears = (int) $obligation->instalments()
            ->where('currency', $period->currency)
            ->whereDate('due_on', '<', today()->toDateString())
            ->whereColumn('paid_minor', '<', 'amount_minor')
            ->sum(DB::raw('amount_minor - paid_minor'));
        $dueInPeriod = (int) $obligation->instalments()
            ->where('currency', $period->currency)
            ->whereBetween('due_on', [$period->starts_on->toDateString(), $period->ends_on->toDateString()])
            ->whereDate('due_on', '>=', today()->toDateString())
            ->whereColumn('paid_minor', '<', 'amount_minor')
            ->sum(DB::raw('amount_minor - paid_minor'));

        return min($balance, $arrears + $dueInPeriod);
    }

    private function compareAnnualRates(Obligation $left, Obligation $right): int
    {
        return bccomp($this->annualRate($left), $this->annualRate($right), 12);
    }

    /** @return numeric-string */
    private function annualRate(Obligation $obligation): string
    {
        $terms = $obligation->chargeTerms;
        if ($terms === null || $terms->rate === null || $terms->basis === 'fixed_total' || $terms->kind === 'none') {
            return '0';
        }

        $multiplier = match ($terms->period) {
            'daily' => '365',
            'monthly' => '12',
            default => '1',
        };
        $rate = (string) $terms->rate;
        if (! is_numeric($rate)) {
            return '0';
        }

        return bcmul($rate, $multiplier, 12);
    }
}
