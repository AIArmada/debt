<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'budget_period_id', 'type', 'category', 'name', 'amount', 'is_essential',
        'is_recurring', 'frequency', 'occurred_on',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'is_essential' => 'boolean', 'is_recurring' => 'boolean', 'occurred_on' => 'date'];
    }

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }
}
