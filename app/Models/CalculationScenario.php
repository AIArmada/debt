<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationScenario extends Model
{
    use HasUuids;

    protected $fillable = ['obligation_id', 'created_by_user_id', 'name', 'extra_payment', 'payment_frequency', 'horizon_months', 'result'];

    protected function casts(): array
    {
        return ['extra_payment' => 'integer', 'horizon_months' => 'integer', 'result' => 'array'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
