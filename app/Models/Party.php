<?php

namespace App\Models;

use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property PartyKind $kind
 * @property PartyStatus $status
 */
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['profile_id', 'kind', 'display_name', 'status'];

    protected function casts(): array
    {
        return ['kind' => PartyKind::class, 'status' => PartyStatus::class];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
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

    public function displayName(): string
    {
        return $this->display_name;
    }
}
