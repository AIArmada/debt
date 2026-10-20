<?php

namespace App\Models;

use Database\Factories\ObligationChargeTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $mode
 * @property string $kind
 * @property string|null $rate
 * @property string|null $basis
 * @property string|null $period
 * @property int $grace_days
 * @property int|null $cap_minor
 * @property int|null $total_payable_minor
 * @property string $penalty_kind
 * @property int|null $penalty_value
 * @property string $allocation_order
 * @property bool $is_formal
 * @property bool $auto_apply_on_acceptance
 * @property Carbon|null $next_charge_on
 * @property Carbon|null $last_charge_on
 * @property string|null $terms_hash
 * @property string|null $accepted_terms_hash
 * @property Carbon|null $accepted_at
 * @property string|null $accepted_name
 * @property Carbon|null $fixed_total_agreed_upfront_at
 * @property string|null $fixed_total_agreed_upfront_by
 * @property Carbon|null $fixed_total_posted_at
 */
class ObligationChargeTerm extends Model
{
    /** @use HasFactory<ObligationChargeTermFactory> */
    use HasFactory;

    protected $primaryKey = 'obligation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'obligation_id', 'mode', 'kind', 'rate', 'basis', 'period', 'grace_days', 'cap_minor',
        'total_payable_minor', 'penalty_kind', 'penalty_value', 'allocation_order', 'is_formal',
        'auto_apply_on_acceptance', 'next_charge_on', 'last_charge_on', 'terms_hash',
        'accepted_terms_hash', 'accepted_at', 'accepted_name', 'fixed_total_agreed_upfront_at',
        'fixed_total_agreed_upfront_by', 'fixed_total_posted_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'grace_days' => 'integer',
            'cap_minor' => 'integer',
            'total_payable_minor' => 'integer',
            'penalty_value' => 'integer',
            'is_formal' => 'boolean',
            'auto_apply_on_acceptance' => 'boolean',
            'next_charge_on' => 'date',
            'last_charge_on' => 'date',
            'accepted_at' => 'datetime',
            'fixed_total_agreed_upfront_at' => 'datetime',
            'fixed_total_posted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fixedTotalAgreedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_total_agreed_upfront_by');
    }
}
