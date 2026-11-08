<?php

namespace App\Features\PromiseTracker\Models;

use App\Features\PromiseTracker\Domain\Enums\NotificationType;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'type', 'enabled'];

    protected function casts(): array
    {
        return ['type' => NotificationType::class, 'enabled' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
