<?php

namespace Database\Factories;

use App\Domain\Enums\ImportRowStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportRow> */
class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    public function definition(): array
    {
        return ['import_batch_id' => ImportBatch::factory(), 'occurred_on' => today(), 'amount_minor' => 1000, 'currency' => 'MYR', 'description' => fake()->sentence(), 'status' => ImportRowStatus::Pending, 'suggested_obligation_id' => null, 'suggested_entry' => null, 'matched_movement_id' => null];
    }
}
