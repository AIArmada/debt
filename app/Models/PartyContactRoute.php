<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyContactRoute extends Model
{
    use HasUuids;

    protected $fillable = [
        'party_id', 'via_party_id', 'via_contact_id', 'created_by_user_id', 'relationship_type', 'purpose',
        'priority', 'is_primary', 'status', 'instructions', 'visibility', 'valid_from', 'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_primary' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function viaParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'via_party_id');
    }

    /** @return BelongsTo<PartyContact, $this> */
    public function viaContact(): BelongsTo
    {
        return $this->belongsTo(PartyContact::class, 'via_contact_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
