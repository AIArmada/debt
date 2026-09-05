<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepaymentInstallment extends Model
{
    use HasUuids;

    protected $fillable = ['obligation_id', 'sequence', 'due_on', 'expected_amount', 'principal_amount', 'interest_amount', 'fee_amount', 'status'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'expected_amount' => 'integer', 'principal_amount' => 'integer', 'interest_amount' => 'integer', 'fee_amount' => 'integer'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
