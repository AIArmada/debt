<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepaymentPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id', 'budget_period_id', 'name', 'strategy', 'available_amount', 'currency', 'status',
        'review_reason', 'review_required_at', 'completed_at', 'superseded_at', 'generated_at',
        'paused_at', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'available_amount' => 'integer',
            'generated_at' => 'datetime',
            'review_required_at' => 'datetime',
            'completed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'paused_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }

    /** @return HasMany<RepaymentPlanAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(RepaymentPlanAllocation::class)->orderBy('priority_rank');
    }

    public function needsReview(): bool
    {
        return $this->status === 'needs_review';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Active plan',
            'needs_review' => 'Needs review',
            'completed' => 'Completed',
            'paused' => 'Paused',
            'superseded' => 'Replaced by a newer plan',
            default => str($this->status)->headline()->toString(),
        };
    }
}
