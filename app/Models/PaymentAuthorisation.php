<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuthorisation extends Model
{
    use HasUuids;

    protected $fillable = ['profile_id', 'payment_schedule_id', 'integration_id', 'authorised_by_user_id', 'provider', 'status', 'max_amount', 'approved_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['max_amount' => 'integer', 'approved_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<PaymentSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by_user_id');
    }
}
