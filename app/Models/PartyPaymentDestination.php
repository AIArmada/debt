<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartyPaymentDestination extends Model
{
    use HasUuids;

    protected $fillable = [
        'party_id', 'created_by_user_id', 'method', 'label', 'provider', 'country_code', 'currency',
        'account_holder_name', 'account_identifier_encrypted', 'account_identifier_last4',
        'reference_template', 'details_encrypted', 'verification_status', 'verified_at',
        'superseded_at', 'status',
    ];

    protected function casts(): array
    {
        return ['account_identifier_encrypted' => 'encrypted', 'details_encrypted' => 'encrypted:array', 'verified_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<ObligationPaymentInstruction, $this> */
    public function obligationInstructions(): HasMany
    {
        return $this->hasMany(ObligationPaymentInstruction::class, 'payment_destination_id');
    }

    public function maskedIdentifier(): string
    {
        return $this->account_identifier_last4 === null ? 'Not provided' : '•••• '.$this->account_identifier_last4;
    }
}
