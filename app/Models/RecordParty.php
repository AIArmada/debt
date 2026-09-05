<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordParty extends Model
{
    use HasUuids;

    protected $fillable = ['record_id', 'party_id', 'created_by_user_id', 'role', 'is_primary', 'responsibility_scope', 'status', 'notes', 'valid_from', 'valid_to', 'visibility'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date'];
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
