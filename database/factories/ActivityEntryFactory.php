<?php

namespace Database\Factories;

use App\Models\ActivityEntry;
use App\Models\FinancialProfile;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ActivityEntry> */
class ActivityEntryFactory extends Factory
{
    protected $model = ActivityEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'actor_user_id' => User::factory(),
            'subject_type' => Record::class,
            'subject_id' => (string) Str::uuid(),
            'action' => 'created',
            'before' => null,
            'after' => [],
            'occurred_at' => now(),
        ];
    }
}
