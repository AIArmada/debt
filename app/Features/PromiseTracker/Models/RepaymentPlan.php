<?php

namespace App\Features\PromiseTracker\Models;

use App\Features\PromiseTracker\Domain\Enums\PlanStatus;
use App\Features\PromiseTracker\Domain\Enums\PlanStrategy;
use Database\Factories\RepaymentPlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PlanStrategy $strategy
 */
class RepaymentPlan extends Model
{
    protected $table = 'repayment_plans';

    /** @use HasFactory<RepaymentPlanFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['budget_period_id', 'status', 'strategy', 'generated_at'];

    protected function casts(): array
    {
        return ['status' => PlanStatus::class, 'strategy' => PlanStrategy::class, 'generated_at' => 'datetime'];
    }

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }

    /** @return HasMany<PlanAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PlanAllocation::class);
    }
}
