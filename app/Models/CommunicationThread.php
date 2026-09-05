<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationThread extends Model
{
    use HasUuids;

    protected $fillable = ['obligation_id', 'channel', 'status'];

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return HasMany<CommunicationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }
}
