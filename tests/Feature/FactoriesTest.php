<?php

namespace Tests\Feature;

use App\Models\FinancialProfile;
use App\Models\MoneyMovement;
use App\Models\ProfileMember;
use App\Models\Record;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_factories_build_a_related_promise_graph(): void
    {
        $movement = MoneyMovement::factory()->create();
        $obligation = $movement->obligation;
        $record = $obligation->record;
        $profile = $record->profile;

        $this->assertSame($obligation->getKey(), $movement->obligation_id);
        $this->assertSame($record->getKey(), $obligation->record_id);
        $this->assertSame($profile->getKey(), $record->profile_id);
        $this->assertDatabaseHas('money_movements', [
            'id' => $movement->getKey(),
            'amount_minor' => 10000,
            'currency' => 'MYR',
        ]);
    }

    public function test_profile_owner_id_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new FinancialProfile(['owner_user_id' => 'attacker']);
    }

    public function test_record_profile_id_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new Record(['profile_id' => 'attacker']);
    }

    public function test_profile_member_identity_attributes_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new ProfileMember(['user_id' => 'attacker']);
    }
}
