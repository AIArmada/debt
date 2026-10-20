<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\SnoozeReminderData;
use App\Domain\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SnoozeReminder
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Reminder $reminder, SnoozeReminderData $data): Reminder
    {
        $reminder->loadMissing('obligation.record.profile');
        Gate::forUser($user)->authorize('update', $reminder->obligation->record);
        if (! in_array($reminder->status, [ReminderStatus::Pending, ReminderStatus::Sent, ReminderStatus::Snoozed], true)) {
            throw ValidationException::withMessages(['reminder' => 'Only active reminders can be snoozed.']);
        }

        return DB::transaction(function () use ($user, $reminder, $data): Reminder {
            $locked = Reminder::query()->whereKey($reminder->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('obligation.record.profile');
            Gate::forUser($user)->authorize('update', $locked->obligation->record);
            if (! in_array($locked->status, [ReminderStatus::Pending, ReminderStatus::Sent, ReminderStatus::Snoozed], true)) {
                throw ValidationException::withMessages(['reminder' => 'Only active reminders can be snoozed.']);
            }
            $locked->forceFill(['snoozed_until' => Carbon::parse($data->until)->toDateString(), 'status' => ReminderStatus::Snoozed])->save();
            $this->activityLogger->record($locked->obligation->record->profile, $user, $locked, 'reminder_snoozed', after: ['snoozed_until' => $locked->snoozed_until->toDateString()]);

            return $locked->refresh();
        });
    }
}
