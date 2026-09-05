<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepaymentPlanAllocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'repayment_plan_id', 'obligation_id', 'currency', 'priority_rank', 'minimum_amount',
        'extra_amount', 'total_amount', 'carried_paid_amount', 'priority_reason', 'projected_completion_on',
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'integer',
            'extra_amount' => 'integer',
            'total_amount' => 'integer',
            'carried_paid_amount' => 'integer',
            'projected_completion_on' => 'date',
        ];
    }

    /** @return BelongsTo<RepaymentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(RepaymentPlan::class, 'repayment_plan_id');
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function paidTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'repayment_plan_allocation_id');
    }
}
