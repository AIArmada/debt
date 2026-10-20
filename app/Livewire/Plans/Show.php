<?php

namespace App\Livewire\Plans;

use App\Actions\Promises\GeneratePlan;
use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\PlanStrategy;
use App\Domain\Queries\DebtFreeProjection;
use App\Domain\Queries\PlanProgress;
use App\Domain\Queries\RecordVisibilityQuery;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\RepaymentPlan;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Show extends Component
{
    public FinancialProfile $profile;

    public BudgetPeriod $period;

    public string $strategy = PlanStrategy::DueDate->value;

    public function mount(FinancialProfile $profile, BudgetPeriod $budgetPeriod): void
    {
        abort_unless($budgetPeriod->profile_id === $profile->getKey(), 404);
        $this->profile = $profile;
        $this->period = $budgetPeriod;
        Gate::authorize('view', $profile);
        $savedStrategy = $budgetPeriod->repaymentPlans()
            ->where('status', PlanStatus::Active->value)
            ->latest('created_at')
            ->value('strategy');
        $strategy = $savedStrategy instanceof PlanStrategy
            ? $savedStrategy
            : PlanStrategy::tryFrom((string) $savedStrategy);
        $this->strategy = ($strategy ?? PlanStrategy::DueDate)->value;
    }

    public function generate(GeneratePlan $generatePlan): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $strategy = PlanStrategy::tryFrom($this->strategy);
        if ($strategy === null) {
            throw ValidationException::withMessages(['strategy' => 'Choose a valid plan strategy.']);
        }
        $generatePlan->handle($user, $this->period, $strategy);
        $this->period->refresh();
    }

    public function render(PlanProgress $planProgress, RecordVisibilityQuery $recordVisibilityQuery, DebtFreeProjection $debtFreeProjection): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $visibleRecordIds = $recordVisibilityQuery
            ->forUser(Record::query(), $user)
            ->select('records.id');
        $visibleObligationIds = Obligation::query()
            ->whereIn('record_id', $visibleRecordIds)
            ->select('obligations.id');
        $plan = $this->period->repaymentPlans()
            ->where('status', PlanStatus::Active->value)
            ->with([
                'allocations' => function (Relation $allocations) use ($visibleObligationIds): void {
                    $allocations->whereIn('obligation_id', $visibleObligationIds);
                },
                'allocations.obligation',
            ])
            ->latest('created_at')
            ->first();

        /** @var array<string, array{planned_minor: int, paid_minor: int}> $progress */
        $progress = $plan instanceof RepaymentPlan ? $planProgress->forPlan($plan) : [];
        $strategy = PlanStrategy::tryFrom($this->strategy) ?? ($plan->strategy ?? PlanStrategy::DueDate);
        $projection = $debtFreeProjection->forPeriod($user, $this->period, $strategy);

        return view('livewire.plans.show', [
            'plan' => $plan,
            'progress' => $progress,
            'strategies' => PlanStrategy::cases(),
            'projection' => $projection,
            'selectedStrategy' => $strategy,
        ])->layout('layouts.app', ['title' => __('plans.repayment_plan')]);
    }
}
