<?php

namespace Database\Factories;

use App\Models\CommitmentSubject;
use App\Models\Obligation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommitmentSubject> */
class CommitmentSubjectFactory extends Factory
{
    protected $model = CommitmentSubject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'done_criteria' => fake()->sentence(),
            'completed_at' => null,
            'completion_note' => null,
        ];
    }
}
