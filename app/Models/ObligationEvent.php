<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $occurred_on
 */
class ObligationEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'created_by_user_id', 'event_type', 'quantity', 'quantity_effect', 'unit',
        'occurred_on', 'note', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'occurred_on' => 'date',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasManyThrough<Document, DocumentLink, $this> */
    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, DocumentLink::class, 'obligation_event_id', 'id', 'id', 'document_id');
    }
}
