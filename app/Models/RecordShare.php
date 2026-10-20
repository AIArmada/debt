<?php

namespace App\Models;

use Database\Factories\RecordShareFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $disputed_at
 */
class RecordShare extends Model
{
    /** @use HasFactory<RecordShareFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'record_id', 'token_hash', 'created_by', 'expires_at', 'revoked_at', 'acknowledged_at',
        'acknowledged_name', 'disputed_at', 'disputed_name', 'dispute_note',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
