<?php

namespace Database\Factories;

use App\Domain\Enums\PartyRole;
use App\Models\Party;
use App\Models\Record;
use App\Models\RecordParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecordParty> */
class RecordPartyFactory extends Factory
{
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
