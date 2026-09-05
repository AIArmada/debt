<?php

namespace App\Models;

use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Record extends Model
{
    /** @use HasFactory<RecordFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['title', 'description', 'sensitivity', 'is_archived'];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return HasMany<RecordParty, $this> */
    public function partyLinks(): HasMany
    {
        return $this->hasMany(RecordParty::class);
    }

    /** @return HasManyThrough<Party, RecordParty, $this> */
    public function parties(): HasManyThrough
    {
        return $this->hasManyThrough(Party::class, RecordParty::class, 'record_id', 'id', 'id', 'party_id');
    }

    public function primaryParty(): ?Party
    {
        $link = $this->relationLoaded('partyLinks')
            ? $this->partyLinks->first(fn (RecordParty $link): bool => $link->is_primary && $link->role === 'other_party')
            : $this->partyLinks()->where('is_primary', true)->where('role', 'other_party')->with('party')->first();

        return $link?->relationLoaded('party') ? $link->party : $link?->party()->first();
    }

    /** @return HasMany<Obligation, $this> */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class)->oldest('created_at')->oldest('id');
    }

    /** @return HasManyThrough<Document, DocumentLink, $this> */
    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, DocumentLink::class, 'record_id', 'id', 'id', 'document_id');
    }

    /** @return HasMany<Obligation, $this> */
    public function openObligations(): HasMany
    {
        return $this->obligations()->where('status', '!=', 'settled');
    }

    public function stateLabel(): string
    {
        $obligations = $this->relationLoaded('obligations') ? $this->obligations : $this->obligations()->get();
        $open = $obligations->where('status', '!=', 'settled')->count();
        $settled = $obligations->where('status', 'settled')->count();

        return match (true) {
            $obligations->isEmpty() => 'No obligations yet',
            $open === 0 => 'All obligations settled',
            $settled > 0 => 'Partly resolved',
            default => 'Open',
        };
    }
}
