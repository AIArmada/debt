<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $next_runs_on
 */
class PaymentSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'mode', 'status', 'amount',
        'currency', 'frequency', 'starts_on', 'ends_on', 'next_runs_on',
        'per_payment_limit', 'period_limit', 'period_limit_frequency',
        'authorised_at', 'paused_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'per_payment_limit' => 'integer',
            'period_limit' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_runs_on' => 'date',
            'authorised_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return HasMany<PaymentAuthorisation, $this> */
    public function authorisations(): HasMany
    {
        return $this->hasMany(PaymentAuthorisation::class, 'payment_schedule_id')->latest();
    }

    /** @return HasMany<PaymentExecutionAttempt, $this> */
    public function executionAttempts(): HasMany
    {
        return $this->hasMany(PaymentExecutionAttempt::class, 'payment_schedule_id')->latest();
    }
}
