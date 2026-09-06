<?php

namespace App\Models;

use App\Domain\Enums\PlanStatus;
use Database\Factories\RepaymentPlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepaymentPlan extends Model
{
    /** @use HasFactory<RepaymentPlanFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['budget_period_id', 'status', 'generated_at'];

    protected function casts(): array
    {
        return ['status' => PlanStatus::class, 'generated_at' => 'datetime'];
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
