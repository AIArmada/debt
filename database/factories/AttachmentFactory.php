<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\AttachmentCategory;
use App\Features\PromiseTracker\Models\Attachment;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attachment> */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attachable_type' => (new Record)->getMorphClass(),
            'attachable_id' => Record::factory(),
            'profile_id' => fn (array $attributes): string => (string) Record::query()
                ->whereKey((string) $attributes['attachable_id'])
                ->value('profile_id'),
            'disk_path' => null,
            'link_url' => fake()->url(),
            'original_name' => fake()->word().'.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 1024,
            'category' => AttachmentCategory::Other,
            'recorded_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
