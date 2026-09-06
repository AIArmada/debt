<?php

namespace App\Models;

use App\Domain\Enums\PartyRole;
use Database\Factories\RecordPartyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PartyRole $role
 * @property-read Party|null $party
 */
class RecordParty extends Model
{
    /** @use HasFactory<RecordPartyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['record_id', 'party_id', 'role', 'is_primary'];

    protected function casts(): array
    {
        return ['role' => PartyRole::class, 'is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
