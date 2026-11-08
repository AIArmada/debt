<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\NotificationType;
use App\Features\PromiseTracker\Models\NotificationPreference;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationPreference> */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationType::ReminderDue,
            'enabled' => true,
        ];
    }
}
