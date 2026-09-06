<?php

namespace Database\Factories;

use App\Models\FinancialProfile;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportBatch> */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return ['profile_id' => FinancialProfile::factory(), 'source_hash' => fake()->sha256(), 'filename' => 'statement.csv', 'row_count' => 0];
    }
}
