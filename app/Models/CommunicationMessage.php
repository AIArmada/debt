<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'communication_thread_id', 'created_by_user_id', 'direction', 'status',
        'recipient', 'body', 'sent_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    /** @return BelongsTo<CommunicationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'communication_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
