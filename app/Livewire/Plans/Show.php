<?php

namespace App\Livewire\Plans;

use App\Actions\Promises\GeneratePlan;
use App\Domain\Enums\PlanStatus;
use App\Domain\Queries\PlanProgress;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\RepaymentPlan;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Show extends Component
{
    public FinancialProfile $profile;

    public BudgetPeriod $period;

    public function mount(FinancialProfile $profile, BudgetPeriod $period): void
    {
        abort_unless($period->profile_id === $profile->getKey(), 404);
        $this->profile = $profile;
        $this->period = $period;
        Gate::authorize('view', $profile);
    }

    public function generate(GeneratePlan $generatePlan): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $generatePlan->handle($user, $this->period);
        $this->period->refresh();
    }

    public function render(PlanProgress $planProgress): View
    {
        $plan = $this->period->repaymentPlans()
            ->where('status', PlanStatus::Active->value)
            ->with('allocations.obligation')
            ->latest('created_at')
            ->first();

        /** @var array<string, array{planned_minor: int, paid_minor: int}> $progress */
        $progress = $plan instanceof RepaymentPlan ? $planProgress->forPlan($plan) : [];

        return view('livewire.plans.show', [
            'plan' => $plan,
            'progress' => $progress,
        ])->layout('layouts.app', ['title' => 'Repayment plan']);
    }
}
