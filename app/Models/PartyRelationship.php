<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyRelationship extends Model
{
    use HasUuids;

    protected $fillable = ['profile_id', 'from_party_id', 'to_party_id', 'relationship_type', 'title', 'status', 'notes', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function fromParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'from_party_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function toParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'to_party_id');
    }
}
