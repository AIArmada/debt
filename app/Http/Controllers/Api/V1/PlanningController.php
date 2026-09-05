<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BudgetPeriod;
use App\Models\RepaymentPlan;
use App\Models\RepaymentPlanAllocation;
use App\Services\ProfileAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlanningController extends Controller
{
    public function budgets(Request $request): JsonResponse
    {
        $validated = $request->validate(['profile_id' => ['required', 'uuid']]);
        $profile = app(ProfileAccess::class)
            ->accessibleProfiles($request->user())
            ->whereKey($validated['profile_id'])
            ->firstOrFail();
        Gate::authorize('view', $profile);

        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $budgets = BudgetPeriod::query()
            ->where('profile_id', $profile->getKey())
            ->withCount('cashFlowEntries')
            ->latest('starts_on')
            ->paginate($perPage);

        return response()->json([
            'data' => $budgets->getCollection()->map(fn (BudgetPeriod $budget): array => [
                'id' => $budget->getKey(),
                'currency' => $budget->currency,
                'starts_on' => $budget->starts_on?->toDateString(),
                'ends_on' => $budget->ends_on?->toDateString(),
                'status' => $budget->status,
                'cash_flow_entries_count' => $budget->cash_flow_entries_count,
            ])->values(),
            'meta' => $this->paginationMeta($budgets),
        ]);
    }

    public function plans(Request $request): JsonResponse
    {
        $validated = $request->validate(['budget_period_id' => ['required', 'uuid']]);
        $budget = BudgetPeriod::query()
            ->whereKey($validated['budget_period_id'])
            ->whereIn('profile_id', app(ProfileAccess::class)->accessibleProfiles($request->user())->select('id'))
            ->firstOrFail();
        Gate::authorize('view', $budget->profile);

        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $plans = $budget->repaymentPlans()
            ->with(['allocations:id,repayment_plan_id,obligation_id,currency,total_amount'])
            ->latest('generated_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $plans->getCollection()->map(fn (RepaymentPlan $plan): array => [
                'id' => $plan->getKey(),
                'name' => $plan->name,
                'strategy' => $plan->strategy,
                'currency' => $plan->currency,
                'status' => $plan->status,
                'generated_at' => $plan->generated_at?->toISOString(),
                'allocations' => $plan->allocations->map(fn (RepaymentPlanAllocation $allocation): array => [
                    'obligation_id' => $allocation->obligation_id,
                    'currency' => $allocation->currency,
                    'total_amount' => $allocation->total_amount,
                ])->values()->all(),
            ])->values(),
            'meta' => $this->paginationMeta($plans),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
