<?php

namespace Tests\Feature;

use App\Models\EmergencyAccessRequest;
use App\Models\FinancialProfile;
use App\Models\FinancialTransaction;
use App\Models\Integration;
use App\Models\ProfileMember;
use App\Models\Record;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_factories_build_a_related_financial_graph(): void
    {
        $transaction = FinancialTransaction::factory()->create();
        $obligation = $transaction->obligation;
        $record = $obligation->record;
        $profile = $record->profile;

        $this->assertSame($obligation->getKey(), $transaction->obligation_id);
        $this->assertSame($record->getKey(), $obligation->record_id);
        $this->assertSame($profile->getKey(), $record->profile_id);
        $this->assertDatabaseHas('financial_transactions', ['id' => $transaction->getKey(), 'currency' => 'MYR']);
    }

    public function test_sensitive_relationship_attributes_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new FinancialProfile(['owner_user_id' => 'attacker']);
    }

    public function test_record_profile_id_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new Record(['profile_id' => 'attacker']);
    }

    public function test_integration_credentials_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new Integration(['credentials_encrypted' => 'attacker-secret']);
    }

    public function test_profile_member_identity_attributes_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new ProfileMember(['user_id' => 'attacker']);
    }

    public function test_emergency_access_approval_attributes_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new EmergencyAccessRequest(['approved_by_user_id' => 'attacker']);
    }
}
