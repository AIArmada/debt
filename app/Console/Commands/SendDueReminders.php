<?php

namespace App\Console\Commands;

use App\Domain\Enums\ReminderStatus;
use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendDueReminders extends Command
{
    protected $signature = 'reminders:send-due';

    protected $description = 'Create database notifications for due promise reminders';

    public function handle(): int
    {
        $count = 0;
        Reminder::query()
            ->whereIn('status', [ReminderStatus::Pending->value, ReminderStatus::Snoozed->value])
            ->whereRaw('COALESCE(snoozed_until, remind_on) <= ?', [today()->toDateString()])
            ->with('obligation.record.profile')
            ->eachById(function (Reminder $reminder) use (&$count): void {
                DB::transaction(function () use ($reminder, &$count): void {
                    $locked = Reminder::query()->whereKey($reminder->getKey())->lockForUpdate()->first();
                    if ($locked === null || ! in_array($locked->status, [ReminderStatus::Pending, ReminderStatus::Snoozed], true)) {
                        return;
                    }

                    $locked->loadMissing('obligation.record.profile');
                    $parent = $locked->obligation->record;
                    $profile = $parent->profile;
                    DB::table('notifications')->insert([
                        'id' => (string) Str::uuid(),
                        'type' => 'reminder_due',
                        'notifiable_id' => $profile->owner_user_id,
                        'notifiable_type' => 'App\\Models\\User',
                        'data' => json_encode([
                            'reminder_id' => $locked->getKey(),
                            'record_id' => $parent?->getKey(),
                            'title' => $parent?->title,
                            'note' => null,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $locked->forceFill(['status' => ReminderStatus::Sent])->save();
                    $count++;
                });
            });

        $this->info("Created {$count} reminder notification(s).");

        return self::SUCCESS;
    }
}
