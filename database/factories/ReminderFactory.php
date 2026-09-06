<?php

namespace Database\Factories;

use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Models\Obligation;
use App\Models\Reminder;
use App\Models\User;
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
