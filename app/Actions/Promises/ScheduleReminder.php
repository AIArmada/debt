<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ScheduleReminderData;
use App\Domain\Enums\ReminderStatus;
use App\Models\Obligation;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ScheduleReminder
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Obligation $obligation, ScheduleReminderData $data): Reminder
    {
        $obligation->loadMissing('record.profile');
        Gate::forUser($user)->authorize('update', $obligation->record);

        return DB::transaction(function () use ($user, $obligation, $data): Reminder {
            $lockedObligation = Obligation::query()->whereKey($obligation->getKey())->lockForUpdate()->firstOrFail();
            $lockedObligation->loadMissing('record.profile');
            Gate::forUser($user)->authorize('update', $lockedObligation->record);
            $reminder = $lockedObligation->reminders()->create([
                'remind_on' => Carbon::parse($data->remindOn)->toDateString(),
                'channel' => $data->channel,
                'status' => ReminderStatus::Pending,
                'created_by' => $user->getKey(),
            ]);
            $this->activityLogger->record($lockedObligation->record->profile, $user, $reminder, 'reminder_scheduled', after: ['remind_on' => $reminder->remind_on->toDateString()]);

            return $reminder;
        });
    }
}
