<?php

namespace Database\Factories;

use App\Domain\Enums\RecordVisibility;
use App\Models\FinancialProfile;
use App\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'created_by' => null,
            'title' => fake()->sentence(3),
            'note' => fake()->optional()->sentence(),
            'is_archived' => false,
            'visibility' => RecordVisibility::Shared,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Record $record): void {
            if ($record->created_by === null) {
                $record->setAttribute('created_by', $record->profile()->value('owner_user_id'));
            }
        });
    }
}
