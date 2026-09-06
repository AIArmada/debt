<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ScheduleReminderData;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Models\Obligation;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ScheduleReminder
{
    public function __construct(private readonly ProfileAccess $profileAccess, private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Obligation $obligation, ScheduleReminderData $data): Reminder
    {
        $obligation->loadMissing('record.profile');
        $profile = $obligation->record->profile;
        if (! $this->profileAccess->can($user, $profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $obligation, $profile, $data): Reminder {
            $reminder = Reminder::query()->create([
                'obligation_id' => $obligation->getKey(),
                'remind_on' => Carbon::parse($data->remindOn)->toDateString(),
                'channel' => ReminderChannel::Database,
                'status' => ReminderStatus::Pending,
                'created_by' => $user->getKey(),
            ]);
            $this->activityLogger->record($profile, $user, $reminder, 'reminder_scheduled', after: ['remind_on' => $reminder->remind_on->toDateString()]);

            return $reminder;
        });
    }
}
