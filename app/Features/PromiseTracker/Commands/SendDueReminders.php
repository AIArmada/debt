<?php

namespace App\Features\PromiseTracker\Commands;

use App\Features\PromiseTracker\Actions\Reminders\SendDueReminders as SendDueRemindersAction;
use Illuminate\Console\Command;

final class SendDueReminders extends Command
{
    protected $signature = 'reminders:send-due';

    protected $description = 'Send notifications for due promise reminders';

    public function handle(SendDueRemindersAction $sendDueReminders): int
    {
        $count = $sendDueReminders->handle();

        $this->info("Created {$count} reminder notification(s).");

        return self::SUCCESS;
    }
}
