<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\PartyRole;
use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\RecordParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecordParty> */
class RecordPartyFactory extends Factory
{
    protected $model = RecordParty::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'party_id' => Party::factory(),
            'role' => PartyRole::Counterparty,
            'is_primary' => true,
        ];
    }
}
