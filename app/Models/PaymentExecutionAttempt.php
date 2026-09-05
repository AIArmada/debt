<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentExecutionAttempt extends Model
{
    use HasUuids;

    protected $fillable = ['payment_schedule_id', 'payment_authorisation_id', 'financial_transaction_id', 'idempotency_key', 'provider', 'amount', 'currency', 'status', 'external_reference', 'response_payload', 'error_message', 'attempted_at', 'completed_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'response_payload' => 'array', 'attempted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<PaymentSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    /** @return BelongsTo<PaymentAuthorisation, $this> */
    public function authorisation(): BelongsTo
    {
        return $this->belongsTo(PaymentAuthorisation::class, 'payment_authorisation_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }
}
