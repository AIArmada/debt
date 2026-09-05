<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'last_used_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
