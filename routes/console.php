<?php

use App\Models\PaymentSchedule;
use App\Services\DueReminderService;
use App\Services\Payments\ExecutePaymentSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('debt:run-schedules', function (ExecutePaymentSchedule $execute): void {
    PaymentSchedule::query()
        ->where('mode', 'automatic')
        ->where('status', 'active')
        ->whereNotNull('next_runs_on')
        ->whereDate('next_runs_on', '<=', today())
        ->lazyById(100)
        ->each(function (PaymentSchedule $schedule) use ($execute): void {
            $execute->handle($schedule);
        });
    $this->info('Due automatic schedules processed.');
})->purpose('Process explicitly authorised automatic payment schedules');

Artisan::command('debt:send-reminders', function (): void {
    $this->info(app(DueReminderService::class)->send().' due reminders sent through enabled channels.');
})->purpose('Send private due-date reminders');
