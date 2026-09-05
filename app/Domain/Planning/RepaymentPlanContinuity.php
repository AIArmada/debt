<?php

namespace App\Domain\Planning;

use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Models\RepaymentPlan;
use App\Models\RepaymentPlanAllocation;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;

final class RepaymentPlanContinuity
{
    public function __construct(
        private readonly ProfileRepaymentSummary $summary,
        private readonly ActivityNotifier $activityNotifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Reconcile plans after a movement is created or corrected.
     *
     * A confirmed payment linked to an allocation updates progress only. Any
     * other confirmed movement in the plan currency changes the assumptions
     * behind the plan and requires an explicit user review.
     *
     * @param  array<string, mixed>  $before
     */
    public function reconcile(Obligation $obligation, FinancialTransaction $movement, array $before = []): void
    {
        $currentCurrency = strtoupper((string) $movement->currency);
        $previousCurrency = strtoupper((string) ($before['currency'] ?? $currentCurrency));
        $impactedCurrencies = collect([$currentCurrency, $previousCurrency])->filter()->unique()->values();

        $plans = RepaymentPlan::query()
            ->where('profile_id', $obligation->record->profile_id)
            ->whereIn('status', ['active', 'needs_review', 'paused', 'completed'])
            ->whereHas('allocations', fn ($query) => $query->where('obligation_id', $obligation->getKey()))
            ->with([
                'profile',
                'budgetPeriod',
                'allocations' => fn ($query) => $query->where('obligation_id', $obligation->getKey()),
            ])
            ->get();

        foreach ($plans as $plan) {
            $allocation = $plan->allocations->firstWhere('obligation_id', $obligation->getKey());
            if (! $allocation instanceof RepaymentPlanAllocation) {
                continue;
            }

            $planCurrency = strtoupper((string) ($allocation->currency ?: $plan->currency));
            if (! $impactedCurrencies->contains($planCurrency)) {
                continue;
            }

            $this->reconcilePlan($plan, $allocation, $movement, $before, $planCurrency);
        }
    }

    /** @param array<string, mixed> $before */
    private function reconcilePlan(
        RepaymentPlan $plan,
        RepaymentPlanAllocation $allocation,
        FinancialTransaction $movement,
        array $before,
        string $planCurrency,
    ): void {
        $entryType = (string) $movement->entry_type;
        $isLinked = (string) $movement->repayment_plan_allocation_id === (string) $allocation->getKey();
        $isInPlanCurrency = strtoupper((string) $movement->currency) === $planCurrency;
        $isCurrentPlanPayment = $isLinked
            && $isInPlanCurrency
            && $entryType === 'payment'
            && $this->isInsidePlanPeriod($movement, $plan);
        $wasConfirmed = ($before['status'] ?? null) === 'confirmed';
        $isConfirmed = $movement->status === 'confirmed';

        if ($isCurrentPlanPayment) {
            if (! $isConfirmed && $wasConfirmed) {
                $this->markNeedsReview($plan, 'A payment linked to this plan is no longer confirmed.');

                return;
            }

            $this->syncCompletion($plan);

            return;
        }

        if ($isConfirmed && $isInPlanCurrency) {
            $reason = $entryType === 'payment'
                ? 'A payment was recorded outside this plan allocation.'
                : 'A new charge or balance adjustment changed this plan\'s assumptions.';
            $this->markNeedsReview($plan, $reason);

            return;
        }

        if ($wasConfirmed && strtoupper((string) ($before['currency'] ?? '')) === $planCurrency) {
            $this->markNeedsReview($plan, 'A confirmed movement linked to this plan was corrected.');
        }
    }

    private function syncCompletion(RepaymentPlan $plan): void
    {
        if (! in_array($plan->status, ['active', 'completed'], true)) {
            return;
        }

        $progress = $this->summary->planProgress($plan->fresh());
        $isComplete = $progress->isNotEmpty()
            && $progress->sum('planned') > 0
            && $progress->every(fn (array $row): bool => $row['paid'] >= $row['planned']);

        $nextStatus = $isComplete ? 'completed' : 'active';
        if ($plan->status === $nextStatus) {
            return;
        }

        $before = $plan->only(['status', 'completed_at']);
        $plan->forceFill([
            'status' => $nextStatus,
            'completed_at' => $isComplete ? now() : null,
        ])->save();

        $this->auditLogger->record(
            $plan->profile,
            null,
            RepaymentPlan::class,
            $plan->getKey(),
            $isComplete ? 'completed' : 'reopened',
            before: $before,
            after: $plan->only(['status', 'completed_at']),
        );
    }

    private function markNeedsReview(RepaymentPlan $plan, string $reason): void
    {
        if ($plan->status === 'needs_review') {
            return;
        }

        $before = $plan->only(['status', 'review_reason', 'review_required_at', 'completed_at']);
        $plan->forceFill([
            'status' => 'needs_review',
            'review_reason' => $reason,
            'review_required_at' => now(),
            'completed_at' => null,
        ])->save();

        $this->auditLogger->record(
            $plan->profile,
            null,
            RepaymentPlan::class,
            $plan->getKey(),
            'needs_review',
            before: $before,
            after: $plan->only(['status', 'review_reason', 'review_required_at', 'completed_at']),
        );

        $this->activityNotifier->notifyProfileActivity(
            $plan->profile,
            'repayment_plan_review_needed',
            'Repayment plan needs review',
            'A confirmed movement changed the assumptions behind a repayment plan.',
            'normal',
            ['plan_id' => $plan->getKey(), 'reason' => $reason],
        );
    }

    private function isInsidePlanPeriod(FinancialTransaction $movement, RepaymentPlan $plan): bool
    {
        $period = $plan->budgetPeriod;
        if ($period === null || $movement->occurred_on === null) {
            return false;
        }

        $date = CarbonImmutable::parse((string) $movement->occurred_on)->toDateString();

        return $date >= CarbonImmutable::parse((string) $period->starts_on)->toDateString()
            && $date <= CarbonImmutable::parse((string) $period->ends_on)->toDateString();
    }
}
