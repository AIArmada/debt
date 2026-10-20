<?php

namespace Database\Factories;

use App\Models\Record;
use App\Models\RecordShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RecordShare> */
class RecordShareFactory extends Factory
{
    protected $model = RecordShare::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'created_by' => User::factory(),
            'expires_at' => null,
            'revoked_at' => null,
            'acknowledged_at' => null,
            'acknowledged_name' => null,
            'disputed_at' => null,
            'disputed_name' => null,
            'dispute_note' => null,
        ];
    }
}
