<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartyAddress extends Model
{
    use HasUuids;

    protected $fillable = [
        'party_id', 'created_by_user_id', 'label', 'address_line_1', 'address_line_2',
        'city', 'region', 'postal_code', 'country_code', 'purpose', 'is_primary',
        'is_verified', 'verified_at', 'visibility', 'valid_from', 'valid_to',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'verified_at' => 'datetime', 'valid_from' => 'date', 'valid_to' => 'date'];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<ObligationDeliveryInstruction, $this> */
    public function deliveryInstructions(): HasMany
    {
        return $this->hasMany(ObligationDeliveryInstruction::class, 'address_id');
    }
}
