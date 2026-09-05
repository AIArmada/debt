<?php

namespace App\Actions\Plans;

use App\Models\RepaymentPlan;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PauseRepaymentPlan
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

            if ($lockedPlan->status !== 'active') {
                throw ValidationException::withMessages([
                    'plan' => 'Only the active repayment plan can be paused.',
                ]);
            }

            $before = $lockedPlan->only(['status', 'paused_at', 'activated_at']);
            $lockedPlan->forceFill([
                'status' => 'paused',
                'paused_at' => now(),
            ])->save();

            $this->auditLogger->record(
                $lockedPlan->profile,
                null,
                RepaymentPlan::class,
                $lockedPlan->getKey(),
                'paused',
                before: $before,
                after: $lockedPlan->only(['status', 'paused_at', 'activated_at']),
            );

            $this->activityNotifier->notifyProfileActivity(
                $lockedPlan->profile,
                'repayment_plan_paused',
                'Repayment plan paused',
                'A repayment plan was paused and can be resumed from plan history.',
                'normal',
                ['plan_id' => $lockedPlan->getKey()],
            );

            return $lockedPlan->fresh();
        });
    }
}
