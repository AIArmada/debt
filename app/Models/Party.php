<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Party extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id', 'created_by_user_id', 'kind', 'preferred_name', 'legal_name',
        'aliases', 'identifiers', 'status', 'verification_status', 'source', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'identifiers' => 'array', 'archived_at' => 'datetime'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<PartyContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class);
    }

    /** @return HasMany<PartyAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(PartyAddress::class);
    }

    /** @return HasMany<PartyRelationship, $this> */
    public function relationshipsFrom(): HasMany
    {
        return $this->hasMany(PartyRelationship::class, 'from_party_id');
    }

    /** @return HasMany<PartyRelationship, $this> */
    public function relationshipsTo(): HasMany
    {
        return $this->hasMany(PartyRelationship::class, 'to_party_id');
    }

    /** @return HasMany<RecordParty, $this> */
    public function recordParties(): HasMany
    {
        return $this->hasMany(RecordParty::class);
    }

    /** @return HasManyThrough<Record, RecordParty, $this> */
    public function records(): HasManyThrough
    {
        return $this->hasManyThrough(Record::class, RecordParty::class, 'party_id', 'id', 'id', 'record_id');
    }

    /** @return HasMany<ObligationParty, $this> */
    public function obligationParties(): HasMany
    {
        return $this->hasMany(ObligationParty::class);
    }

    /** @return HasMany<PartyPaymentDestination, $this> */
    public function paymentDestinations(): HasMany
    {
        return $this->hasMany(PartyPaymentDestination::class);
    }

    /** @return HasMany<PartyContactRoute, $this> */
    public function contactRoutes(): HasMany
    {
        return $this->hasMany(PartyContactRoute::class);
    }

    /** @return HasMany<PartyContactRoute, $this> */
    public function routesThrough(): HasMany
    {
        return $this->hasMany(PartyContactRoute::class, 'via_party_id');
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'individual' => 'Person',
            'organization' => 'Organization',
            'group' => 'Group or household',
            'estate_or_trust' => 'Estate or trust',
            default => 'Unidentified party',
        };
    }
}
