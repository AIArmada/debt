<?php

namespace App\Actions\Promises;

use App\Domain\Enums\MemberRole;
use App\Domain\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DismissReminder
{
    public function __construct(private readonly ProfileAccess $profileAccess, private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Reminder $reminder): Reminder
    {
        $reminder->loadMissing('obligation.record.profile');
        if (! $this->profileAccess->can($user, $reminder->obligation->record->profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }
        if (! in_array($reminder->status, [ReminderStatus::Pending, ReminderStatus::Snoozed], true)) {
            throw ValidationException::withMessages(['reminder' => 'Only scheduled reminders can be dismissed.']);
        }

        return DB::transaction(function () use ($user, $reminder): Reminder {
            $locked = Reminder::query()->whereKey($reminder->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('obligation.record.profile');
            $locked->forceFill(['status' => ReminderStatus::Dismissed])->save();
            $this->activityLogger->record($locked->obligation->record->profile, $user, $locked, 'reminder_dismissed');

            return $locked->refresh();
        });
    }
}
