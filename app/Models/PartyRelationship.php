<?php

namespace App\Models;

use App\Domain\Enums\PartyRelationshipKind;
use Database\Factories\PartyRelationshipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property PartyRelationshipKind $kind */
class PartyRelationship extends Model
{
    /** @use HasFactory<PartyRelationshipFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['profile_id', 'from_party_id', 'to_party_id', 'kind', 'created_by'];

    protected function casts(): array
    {
        return ['kind' => PartyRelationshipKind::class];
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
