<?php

namespace App\Actions\Plans;

use App\Models\RepaymentPlan;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ActivateRepaymentPlan
{
    public function __construct(
        private readonly ActivityNotifier $activityNotifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(RepaymentPlan $plan): RepaymentPlan
    {
        Gate::authorize('manageBudget', $plan->profile);

        return DB::transaction(function () use ($plan): RepaymentPlan {
            $lockedPlan = RepaymentPlan::query()
                ->with('profile')
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPlan->status !== 'paused') {
                throw ValidationException::withMessages([
                    'plan' => 'Only a paused plan without pending review can be activated directly.',
                ]);
            }

            $activePlan = RepaymentPlan::query()
                ->where('profile_id', $lockedPlan->profile_id)
                ->where('budget_period_id', $lockedPlan->budget_period_id)
                ->where('currency', $lockedPlan->currency)
                ->where('status', 'active')
                ->whereKeyNot($lockedPlan->getKey())
                ->lockForUpdate()
                ->first();

            if ($activePlan !== null) {
                $activeBefore = $activePlan->only(['status', 'paused_at']);
                $activePlan->forceFill([
                    'status' => 'paused',
                    'paused_at' => now(),
                ])->save();

                $this->auditLogger->record(
                    $lockedPlan->profile,
                    null,
                    RepaymentPlan::class,
                    $activePlan->getKey(),
                    'paused_for_activation',
                    before: $activeBefore,
                    after: $activePlan->only(['status', 'paused_at']),
                    metadata: ['activated_plan_id' => $lockedPlan->getKey()],
                );
            }

            $before = $lockedPlan->only(['status', 'paused_at', 'activated_at']);
            $lockedPlan->forceFill([
                'status' => 'active',
                'paused_at' => null,
                'activated_at' => now(),
            ])->save();

            $this->auditLogger->record(
                $lockedPlan->profile,
                null,
                RepaymentPlan::class,
                $lockedPlan->getKey(),
                'activated',
                before: $before,
                after: $lockedPlan->only(['status', 'paused_at', 'activated_at']),
                metadata: ['paused_plan_id' => $activePlan?->getKey()],
            );

            $this->activityNotifier->notifyProfileActivity(
                $lockedPlan->profile,
                'repayment_plan_activated',
                'Repayment plan activated',
                'A repayment plan was activated from plan history.',
                'normal',
                ['plan_id' => $lockedPlan->getKey(), 'paused_plan_id' => $activePlan?->getKey()],
            );

            return $lockedPlan->fresh();
        });
    }
}
