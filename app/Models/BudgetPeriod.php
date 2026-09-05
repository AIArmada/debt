<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPeriod extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id', 'currency', 'starts_on', 'ends_on', 'emergency_reserve_amount',
        'available_for_obligations_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'emergency_reserve_amount' => 'integer',
            'available_for_obligations_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return HasMany<CashFlowEntry, $this> */
    public function cashFlowEntries(): HasMany
    {
        return $this->hasMany(CashFlowEntry::class);
    }

    /** @return HasMany<RepaymentPlan, $this> */
    public function repaymentPlans(): HasMany
    {
        return $this->hasMany(RepaymentPlan::class);
    }
}
