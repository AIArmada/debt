<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'email_enabled', 'in_app_enabled', 'push_enabled', 'generic_push', 'due_reminder_days'];

    protected function casts(): array
    {
        return ['email_enabled' => 'boolean', 'in_app_enabled' => 'boolean', 'push_enabled' => 'boolean', 'generic_push' => 'boolean', 'due_reminder_days' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
