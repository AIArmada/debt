<?php

namespace App\Features\PromiseTracker\Models;

use App\Features\PromiseTracker\Domain\Enums\ReminderChannel;
use App\Features\PromiseTracker\Domain\Enums\ReminderStatus;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ReminderChannel $channel
 * @property ReminderStatus $status
 * @property Carbon $remind_on
 * @property Carbon|null $snoozed_until
 */
class Reminder extends Model
{
    protected $table = 'reminders';

    /** @use HasFactory<ReminderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['obligation_id', 'remind_on', 'snoozed_until', 'channel', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['channel' => ReminderChannel::class, 'status' => ReminderStatus::class, 'remind_on' => 'date', 'snoozed_until' => 'date'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
