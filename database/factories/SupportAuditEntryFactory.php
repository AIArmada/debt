<?php

namespace Database\Factories;

use App\Models\SupportAuditEntry;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportAuditEntry>
 */
class SupportAuditEntryFactory extends Factory
{
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
