<?php

namespace App\Models;

use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\PartyRole;
use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Record extends Model
{
    /** @use HasFactory<RecordFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['title', 'note', 'is_archived'];

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
    public function counterparties(): HasManyThrough
    {
        return $this->hasManyThrough(Party::class, RecordParty::class, 'record_id', 'id', 'id', 'party_id')
            ->where('record_parties.role', PartyRole::Counterparty->value);
    }

    /** @return HasMany<Obligation, $this> */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class)->oldest('created_at')->oldest('id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function hasConfirmedMovements(): bool
    {
        return $this->obligations()
            ->whereHas('moneyMovements', fn (Builder $query): Builder => $query->where('status', MovementStatus::Confirmed->value))
            ->exists()
            || $this->obligations()
                ->whereHas('quantityReturns', fn (Builder $query): Builder => $query->where('status', MovementStatus::Confirmed->value))
                ->exists();
    }

    public function canBeDeleted(): bool
    {
        if ($this->hasConfirmedMovements()) {
            return false;
        }

        if ($this->obligations()->whereHas('quantityReturns')->exists()) {
            return false;
        }

        return ! $this->hasAnyAttachments();
    }

    private function hasAnyAttachments(): bool
    {
        return $this->attachments()->exists()
            || $this->obligations()->whereHas('attachments')->exists()
            || $this->obligations()->whereHas('moneyMovements', fn (Builder $query): Builder => $query->whereHas('attachments'))->exists()
            || $this->obligations()->whereHas('quantityReturns', fn (Builder $query): Builder => $query->whereHas('attachments'))->exists();
    }
}
