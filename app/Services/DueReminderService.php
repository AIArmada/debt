<?php

namespace App\Services;

use App\Models\FinancialProfile;
use App\Notifications\DebtDueNotification;
use Carbon\CarbonImmutable;

class DueReminderService
{
    public function send(): int
    {
        $sent = 0;
        $profiles = FinancialProfile::query()->where('is_archived', false)->with(['owner', 'members.user', 'records.obligations' => fn ($query) => $query->active()->whereNotNull('next_due_on')])->get();
        foreach ($profiles as $profile) {
            $recipients = collect([$profile->owner])->merge($profile->members->map(fn ($member) => $member->user))->filter(fn ($user): bool => $user !== null && app(ProfileAccess::class)->role($user, $profile) !== null)->unique('id');
            foreach ($recipients as $user) {
                $days = (int) $user->notificationPreference()->firstOrCreate(['user_id' => $user->getKey()], ['email_enabled' => true, 'in_app_enabled' => true, 'push_enabled' => false, 'generic_push' => true, 'due_reminder_days' => 3])->due_reminder_days;
                foreach ($profile->records->flatMap(fn ($record) => $record->obligations) as $obligation) {
                    $dueOn = $obligation->next_due_on?->toDateString();
                    $from = CarbonImmutable::today()->toDateString();
                    $until = CarbonImmutable::today()->addDays($days)->toDateString();
                    if (is_string($dueOn) && $dueOn >= $from && $dueOn <= $until) {
                        $user->notify(new DebtDueNotification($obligation));
                        $sent++;
                    }
                }
            }
        }

        return $sent;
    }
}
