<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Models\SupportAuditEntry;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportAuditEntry>
 */
class SupportAuditEntryFactory extends Factory
{
    protected $model = SupportAuditEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'case_id' => SupportCase::factory(),
            'profile_id' => null,
            'action' => 'support_case.opened',
            'subject_type' => null,
            'subject_id' => null,
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
