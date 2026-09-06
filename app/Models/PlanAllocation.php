<?php

namespace App\Models;

use Database\Factories\PlanAllocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAllocation extends Model
{
    /** @use HasFactory<PlanAllocationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['repayment_plan_id', 'obligation_id', 'planned_minor'];

    protected function casts(): array
    {
        return ['planned_minor' => 'integer'];
    }

    /** @return BelongsTo<RepaymentPlan, $this> */
    public function repaymentPlan(): BelongsTo
    {
        return $this->belongsTo(RepaymentPlan::class);
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
