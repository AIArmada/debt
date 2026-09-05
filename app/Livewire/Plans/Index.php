<?php

namespace App\Livewire\Plans;

use App\Actions\Plans\ActivateRepaymentPlan;
use App\Actions\Plans\AddCashFlowEntry;
use App\Actions\Plans\CreateBudgetPeriod;
use App\Actions\Plans\GenerateRepaymentPlan;
use App\Actions\Plans\PauseRepaymentPlan;
use App\Domain\Planning\BudgetCapacity;
use App\Domain\Planning\ProfileRepaymentSummary;
use App\Domain\Planning\RepaymentPlanRefreshPreview;
use App\Livewire\Concerns\InteractsWithAccessibleProfiles;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\RepaymentPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAccessibleProfiles;

    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public ?string $budgetPeriodId = null;

    #[Validate('required|date')]
    public string $startsOn = '';

    #[Validate('required|date|after_or_equal:startsOn')]
    public string $endsOn = '';

    #[Validate('required|numeric|min:0')]
    public string $emergencyReserveAmount = '0';

    #[Validate('required|in:income,expense')]
    public string $cashFlowType = 'income';

    #[Validate('required|string|max:60')]
    public string $cashFlowCategory = 'salary';

    #[Validate('required|string|max:100')]
    public string $cashFlowName = '';

    #[Validate('required|numeric|gt:0')]
    public ?string $cashFlowAmount = null;

    #[Validate('boolean')]
    public bool $cashFlowEssential = false;

    #[Validate('boolean')]
    public bool $cashFlowRecurring = true;

    #[Validate('required|in:highest_interest,smallest_balance,earliest_due,manual')]
    public string $strategy = 'highest_interest';

    public ?string $planId = null;

    /** @var array<int, string> */
    public array $selectedObligationIds = [];

    public ?string $selectionBudgetId = null;

    public ?string $activationPlanId = null;

    /** @var array{currency?: string, previous_available?: int, current_available?: int, warning?: string|null, rows?: array<int, array{title: string, current: int, previous: int, proposed: int, currency: string}>, has_changes?: bool} */
    public array $activationPreview = [];

    private ?BudgetPeriod $currentBudgetCache = null;

    private ?string $currentBudgetCacheKey = null;

    private bool $currentBudgetResolved = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', FinancialProfile::class);
        $profiles = $this->accessibleProfilesCollection();
        $selectedProfileId = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $profiles->contains('id', $selectedProfileId)
            ? $selectedProfileId
            : $profiles->first()?->getKey();
        $this->startsOn = today()->startOfMonth()->toDateString();
        $this->endsOn = today()->endOfMonth()->toDateString();
        $this->cashFlowName = 'Monthly income';
        $this->budgetPeriodId = $this->currentBudget()?->getKey();
        $this->planId = $this->currentPlanId();
    }

    public function updatedProfileId(): void
    {
        $this->accessibleProfile($this->profileId);
        session()->put('selected_profile_id', $this->profileId);
        $this->clearCurrentBudgetCache();
        $this->budgetPeriodId = $this->currentBudget()?->getKey();
        $this->planId = $this->currentPlanId();
        $this->selectionBudgetId = null;
        $this->selectedObligationIds = [];
    }

    public function createBudget(CreateBudgetPeriod $createBudgetPeriod): void
    {
        $validated = $this->validate([
            'startsOn' => 'required|date',
            'endsOn' => 'required|date|after_or_equal:startsOn',
            'emergencyReserveAmount' => 'required|numeric|min:0',
        ]);
        $profile = $this->accessibleProfile($this->profileId);
        $budget = $createBudgetPeriod->handle(Auth::user(), $profile, [
            'currency' => $profile->base_currency,
            'starts_on' => $validated['startsOn'],
            'ends_on' => $validated['endsOn'],
            'emergency_reserve_amount' => $validated['emergencyReserveAmount'],
        ]);
        $this->budgetPeriodId = $budget->getKey();
        $this->currentBudgetCache = $budget->load('cashFlowEntries');
        $this->currentBudgetCacheKey = $this->budgetCacheKey();
        $this->currentBudgetResolved = true;
        $this->planId = null;
        $this->selectionBudgetId = null;
        $this->selectedObligationIds = [];
        session()->flash('budget-created', 'The budget period was created.');
    }

    public function addCashFlow(AddCashFlowEntry $addCashFlowEntry): void
    {
        $validated = $this->validate([
            'cashFlowType' => 'required|in:income,expense',
            'cashFlowCategory' => 'required|string|max:60',
            'cashFlowName' => 'required|string|max:100',
            'cashFlowAmount' => 'required|numeric|gt:0',
            'cashFlowEssential' => 'boolean',
            'cashFlowRecurring' => 'boolean',
        ]);
        $budget = $this->currentBudget();

        if ($budget === null) {
            $this->addError('cashFlowAmount', 'Create a budget period first.');

            return;
        }

        $addCashFlowEntry->handle($budget, [
            'type' => $validated['cashFlowType'],
            'category' => $validated['cashFlowCategory'],
            'name' => $validated['cashFlowName'],
            'amount' => $validated['cashFlowAmount'],
            'is_essential' => $validated['cashFlowEssential'],
            'is_recurring' => $validated['cashFlowRecurring'],
        ]);
        $this->clearCurrentBudgetCache();
        $this->reset('cashFlowAmount');
        session()->flash('cash-flow-added', 'The cash-flow entry was added.');
    }

    public function generatePlan(GenerateRepaymentPlan $generateRepaymentPlan): void
    {
        $this->validateOnly('strategy');
        $budget = $this->currentBudget();

        if ($budget === null) {
            $this->addError('strategy', 'Create a budget period first.');

            return;
        }

        if ($this->selectedObligationIds === []) {
            $this->addError('selectedObligationIds', 'Select at least one money obligation to include in the plan.');

            return;
        }

        $candidateIds = app(ProfileRepaymentSummary::class)
            ->candidateRows($budget)
            ->pluck('obligation_id')
            ->all();
        $selectedIds = collect($this->selectedObligationIds)
            ->map(fn ($id): string => (string) $id)
            ->intersect($candidateIds)
            ->values()
            ->all();

        if ($selectedIds === []) {
            $this->addError('selectedObligationIds', 'Choose an eligible payable money obligation in this budget currency.');

            return;
        }

        $currentPlan = $this->currentPlan();
        $refreshingPlan = $currentPlan?->needsReview() ? $currentPlan : null;
        $plan = $generateRepaymentPlan->handle($budget, $this->strategy, $selectedIds, $refreshingPlan);
        $this->planId = $plan->getKey();
        $this->selectionBudgetId = (string) $budget->getKey();
        $this->selectedObligationIds = $selectedIds;
        session()->flash(
            'plan-created',
            $refreshingPlan !== null
                ? 'The repayment plan was recalculated from current balances. The previous plan remains in history.'
                : ($currentPlan !== null
                    ? 'A new repayment plan is active. The previous plan was paused and remains available in history.'
                    : 'A repayment plan was generated from your current budget.'),
        );
    }

    public function pausePlan(string $planId, PauseRepaymentPlan $pauseRepaymentPlan): void
    {
        $plan = $this->accessiblePlan($planId);
        $pauseRepaymentPlan->handle($plan);
        $this->planId = $this->currentPlanId();
        session()->flash('plan-paused', 'The repayment plan was paused. You can activate it again from plan history.');
    }

    public function activatePlan(string $planId, ActivateRepaymentPlan $activateRepaymentPlan, RepaymentPlanRefreshPreview $refreshPreview): void
    {
        $plan = $this->accessiblePlan($planId);

        if ($plan->status === 'needs_review') {
            $this->activationPlanId = (string) $plan->getKey();
            $this->activationPreview = $refreshPreview->build($plan);
            $this->modal('plan-activation')->show();

            return;
        }

        if ($plan->status !== 'paused') {
            $this->addError('planActivation', 'Only a paused plan can be activated directly.');

            return;
        }

        $activateRepaymentPlan->handle($plan);
        $this->planId = (string) $plan->getKey();
        session()->flash('plan-activated', 'The repayment plan is active again.');
    }

    public function refreshAndActivatePlan(GenerateRepaymentPlan $generateRepaymentPlan): void
    {
        if ($this->activationPlanId === null) {
            return;
        }

        $plan = $this->accessiblePlan($this->activationPlanId);
        if ($plan->status !== 'needs_review' || $plan->budgetPeriod === null) {
            $this->addError('planActivation', 'This plan cannot be refreshed because its budget period is no longer available.');

            return;
        }

        $obligationIds = $plan->allocations()->pluck('obligation_id')->map(fn ($id): string => (string) $id)->all();
        $newPlan = $generateRepaymentPlan->handle($plan->budgetPeriod, $plan->strategy, $obligationIds, $plan);
        $this->planId = (string) $newPlan->getKey();
        $this->selectionBudgetId = (string) $plan->budgetPeriod->getKey();
        $this->selectedObligationIds = $obligationIds;
        $this->activationPlanId = null;
        $this->activationPreview = [];
        $this->modal('plan-activation')->close();
        session()->flash('plan-refreshed', 'The plan was refreshed from current balances and activated. Earlier payments remain in its progress history.');
    }

    public function cancelPlanActivation(): void
    {
        $this->activationPlanId = null;
        $this->activationPreview = [];
        $this->modal('plan-activation')->close();
    }

    public function selectAllObligations(): void
    {
        $budget = $this->currentBudget();
        $this->selectedObligationIds = $budget === null
            ? []
            : app(ProfileRepaymentSummary::class)->candidateRows($budget)->pluck('obligation_id')->all();
        $this->resetValidation('selectedObligationIds');
    }

    public function clearObligations(): void
    {
        $this->selectedObligationIds = [];
        $this->resetValidation('selectedObligationIds');
    }

    #[On('transaction-recorded')]
    #[On('transaction-updated')]
    public function refreshPlanProgress(): void
    {
        // The listener causes the plan and its progress collection to be reloaded.
    }

    public function render(): View
    {
        Gate::authorize('viewAny', FinancialProfile::class);
        $profiles = $this->accessibleProfilesCollection();
        $profile = $this->accessibleProfile($this->profileId);
        $role = $this->accessibleProfileRole($this->profileId);
        $canManageBudget = in_array($role, ['owner', 'editor'], true);
        $canRecordTransactions = in_array($role, ['owner', 'editor', 'payment_manager'], true);
        $budget = $this->currentBudget();
        $capacityService = app(BudgetCapacity::class);
        $capacityBreakdown = $budget === null ? null : $capacityService->breakdown($budget->loadMissing('cashFlowEntries'));
        $capacity = $capacityBreakdown['available_to_plan'] ?? 0;
        $plan = $this->planId === null ? null : RepaymentPlan::query()
            ->where('profile_id', $this->profileId)
            ->whereIn('status', ['active', 'needs_review'])
            ->with('allocations.obligation')
            ->find($this->planId);
        $plans = $budget === null ? collect() : $budget->repaymentPlans()
            ->withCount('allocations')
            ->latest('generated_at')
            ->get();
        $summaryService = app(ProfileRepaymentSummary::class);
        $candidates = $budget === null ? collect() : $summaryService->candidateRows($budget, $plan);
        $excludedCandidates = $budget === null ? collect() : $summaryService->excludedCurrencyRows($budget);
        $budgetKey = $budget?->getKey();
        if ($budgetKey !== $this->selectionBudgetId) {
            $this->selectionBudgetId = $budgetKey;
            $this->selectedObligationIds = $candidates->pluck('obligation_id')->all();
        } else {
            $candidateIds = $candidates->pluck('obligation_id')->all();
            $this->selectedObligationIds = collect($this->selectedObligationIds)->intersect($candidateIds)->values()->all();
        }
        $planProgress = $plan === null ? collect() : $summaryService->planProgress($plan);

        return view('livewire.plans.index', [
            'profiles' => $profiles,
            'canManageBudget' => $canManageBudget,
            'canRecordTransactions' => $canRecordTransactions,
            'budget' => $budget,
            'capacity' => $capacity,
            'capacityBreakdown' => $capacityBreakdown,
            'plan' => $plan,
            'plans' => $plans,
            'candidates' => $candidates,
            'excludedCandidates' => $excludedCandidates,
            'planProgress' => $planProgress,
        ])->layout('layouts.app', ['title' => 'Budget & plans']);
    }

    private function currentBudget(): ?BudgetPeriod
    {
        $cacheKey = $this->budgetCacheKey();
        if ($this->currentBudgetResolved && $this->currentBudgetCacheKey === $cacheKey) {
            return $this->currentBudgetCache;
        }

        $this->currentBudgetCacheKey = $cacheKey;
        $this->currentBudgetResolved = true;
        if ($this->budgetPeriodId !== null) {
            return $this->currentBudgetCache = BudgetPeriod::query()
                ->whereKey($this->budgetPeriodId)
                ->where('profile_id', $this->profileId)
                ->with('cashFlowEntries')
                ->first();
        }

        return $this->currentBudgetCache = BudgetPeriod::query()
            ->where('profile_id', $this->profileId)
            ->where('status', 'open')
            ->latest('starts_on')
            ->with('cashFlowEntries')
            ->first();
    }

    private function clearCurrentBudgetCache(): void
    {
        $this->currentBudgetCache = null;
        $this->currentBudgetCacheKey = null;
        $this->currentBudgetResolved = false;
    }

    private function budgetCacheKey(): string
    {
        return (string) $this->profileId.'|'.(string) $this->budgetPeriodId;
    }

    private function currentPlan(): ?RepaymentPlan
    {
        if ($this->planId === null) {
            return null;
        }

        return RepaymentPlan::query()
            ->where('profile_id', $this->profileId)
            ->whereIn('status', ['active', 'needs_review'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('generated_at')
            ->find($this->planId);
    }

    private function currentPlanId(): ?string
    {
        $budget = $this->currentBudget();
        if ($budget === null) {
            return null;
        }

        $id = $budget->repaymentPlans()
            ->whereIn('status', ['active', 'needs_review'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('generated_at')
            ->value('id');

        return $id === null ? null : (string) $id;
    }

    private function accessiblePlan(string $planId): RepaymentPlan
    {
        return RepaymentPlan::query()
            ->where('profile_id', $this->profileId)
            ->where('budget_period_id', $this->budgetPeriodId)
            ->with('budgetPeriod')
            ->findOrFail($planId);
    }
}
