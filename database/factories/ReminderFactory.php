<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\ReminderChannel;
use App\Features\PromiseTracker\Domain\Enums\ReminderStatus;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\Reminder;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reminder> */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'remind_on' => today()->addDay(),
            'snoozed_until' => null,
            'channel' => ReminderChannel::Database,
            'status' => ReminderStatus::Pending,
            'created_by' => User::factory(),
        ];
    }
}
