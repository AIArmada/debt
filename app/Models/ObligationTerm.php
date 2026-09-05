<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationTerm extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'version', 'calculation_method', 'interest_rate',
        'interest_period', 'compounding_period', 'late_fee_amount',
        'late_fee_rate', 'storage_fee_amount', 'storage_fee_period', 'formula',
        'fixed_installment_amount',
        'source_snapshot', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:8',
            'late_fee_amount' => 'integer',
            'late_fee_rate' => 'decimal:8',
            'storage_fee_amount' => 'integer',
            'fixed_installment_amount' => 'integer',
            'formula' => 'array',
            'source_snapshot' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
