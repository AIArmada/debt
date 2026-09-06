<?php

namespace App\Actions\Promises;

use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Models\BudgetPeriod;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\RepaymentPlan;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class GeneratePlan
{
    public function __construct(
        private readonly ProfileAccess $profileAccess,
        private readonly OutstandingBalance $outstandingBalance,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, BudgetPeriod $period): RepaymentPlan
    {
        $period->loadMissing('profile');
        if (! $this->profileAccess->can($user, $period->profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $period): RepaymentPlan {
            $period->repaymentPlans()->where('status', PlanStatus::Active->value)->update(['status' => PlanStatus::Superseded]);
            $plan = $period->repaymentPlans()->create(['status' => PlanStatus::Active, 'generated_at' => now()]);
            $capacity = $period->capacity();
            $obligations = $period->profile->records()
                ->where('is_archived', false)
                ->with('obligations')
                ->get()
                ->flatMap(static fn (Record $record) => $record->obligations)
                ->filter(static fn (Obligation $obligation): bool => $obligation->subject_type === SubjectType::Money && $obligation->status === ObligationStatus::Open && $obligation->direction === Direction::Payable)
                ->map(function (Obligation $obligation) use ($period): array {
                    $balance = $this->outstandingBalance->forObligation($obligation)[$period->currency] ?? 0;

                    return ['obligation' => $obligation, 'balance' => max(0, $balance)];
                })
                ->filter(static fn (array $item): bool => $item['balance'] > 0)
                ->sort(function (array $left, array $right): int {
                    $leftDate = $left['obligation']->due_on?->getTimestamp() ?? PHP_INT_MAX;
                    $rightDate = $right['obligation']->due_on?->getTimestamp() ?? PHP_INT_MAX;

                    return $leftDate <=> $rightDate ?: $right['balance'] <=> $left['balance'];
                });

            foreach ($obligations as $item) {
                if ($capacity <= 0) {
                    break;
                }
                $planned = min($capacity, $item['balance']);
                $plan->allocations()->create(['obligation_id' => $item['obligation']->getKey(), 'planned_minor' => $planned]);
                $capacity -= $planned;
            }
            $this->activityLogger->record($period->profile, $user, $plan, 'repayment_plan_generated', after: ['plan_id' => $plan->getKey(), 'capacity_minor' => $period->capacity()]);

            return $plan->load('allocations');
        });
    }
}
