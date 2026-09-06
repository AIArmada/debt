<?php

namespace Database\Factories;

use App\Domain\Enums\Direction;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Models\Obligation;
use App\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obligation>
 */
class ObligationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'direction' => Direction::Payable,
            'title' => fake()->sentence(3),
            'status' => ObligationStatus::Open,
            'subject_type' => SubjectType::Money,
            'due_on' => null,
        ];
    }
}
