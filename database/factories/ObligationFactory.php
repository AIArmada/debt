<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\Direction;
use App\Features\PromiseTracker\Domain\Enums\ObligationStatus;
use App\Features\PromiseTracker\Domain\Enums\SubjectType;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obligation>
 */
class ObligationFactory extends Factory
{
    protected $model = Obligation::class;

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
