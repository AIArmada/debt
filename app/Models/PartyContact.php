<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon|null $verified_at */
class PartyContact extends Model
{
    use HasUuids;

    protected $fillable = [
        'party_id', 'created_by_user_id', 'type', 'label', 'value', 'purpose',
        'is_primary', 'is_verified', 'verified_at', 'is_message_safe', 'visibility',
        'valid_from', 'valid_to',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'is_message_safe' => 'boolean', 'verified_at' => 'datetime', 'valid_from' => 'date', 'valid_to' => 'date'];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<PartyContactRoute, $this> */
    public function contactRoutes(): HasMany
    {
        return $this->hasMany(PartyContactRoute::class, 'via_contact_id');
    }
}
