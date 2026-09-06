<?php

namespace App\Models;

use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['profile_id', 'starts_on', 'ends_on', 'income_minor', 'essential_minor', 'reserve_minor', 'currency'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'income_minor' => 'integer', 'essential_minor' => 'integer', 'reserve_minor' => 'integer'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return HasMany<RepaymentPlan, $this> */
    public function repaymentPlans(): HasMany
    {
        return $this->hasMany(RepaymentPlan::class);
    }

    public function capacity(): int
    {
        return max(0, $this->income_minor - $this->essential_minor - $this->reserve_minor);
    }
}
