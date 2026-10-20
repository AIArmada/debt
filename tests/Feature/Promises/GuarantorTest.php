<?php

namespace Tests\Feature\Promises;

use App\Actions\Promises\AddRecordGuarantor;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\CreateRecordShare;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\CreateRecordShareData;
use App\Actions\Promises\Data\RecordGuarantorData;
use App\Actions\Promises\RemoveRecordGuarantor;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PartyStatus;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarantors_can_be_added_removed_and_are_shown_on_a_public_share(): void
    {
        $owner = User::factory()->create();
        $profile = $owner->financialProfiles()->firstOrFail();
        $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Primary counterparty',
            'direction' => Direction::Payable->value,
            'amount' => '45.00',
        ], (string) $profile->base_currency));
        $existingParty = $profile->parties()->create([
            'kind' => PartyKind::Individual,
            'display_name' => 'Existing guarantor',
            'status' => PartyStatus::Active,
        ]);
        $addGuarantor = app(AddRecordGuarantor::class);
        $newLink = $addGuarantor->handle($owner, $record, RecordGuarantorData::fromInput(['partyName' => 'New guarantor']));
        $existingLink = $addGuarantor->handle($owner, $record, RecordGuarantorData::fromInput(['partyId' => $existingParty->getKey()]));

        self::assertSame(PartyRole::Guarantor, $newLink->role);
        self::assertFalse($newLink->is_primary);
        self::assertSame('New guarantor', $newLink->party->display_name);
        self::assertSame($existingParty->getKey(), $existingLink->party_id);
        $this->assertDatabaseHas('activity_entries', ['action' => 'record_guarantor_added', 'subject_id' => $newLink->getKey()]);

        $share = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));
        $this->get(route('shares.show', ['token' => $share['token']]))
            ->assertOk()
            ->assertSee('New guarantor')
            ->assertSee('Existing guarantor');

        app(RemoveRecordGuarantor::class)->handle($owner, $newLink);
        $this->assertDatabaseMissing('record_parties', ['id' => $newLink->getKey()]);
        $this->assertDatabaseHas('activity_entries', ['action' => 'record_guarantor_removed', 'subject_id' => $newLink->getKey()]);
    }

    public function test_viewer_cannot_add_a_guarantor(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $profile = $owner->financialProfiles()->firstOrFail();
        $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Protected promise',
            'direction' => Direction::Payable->value,
            'amount' => '10.00',
        ], (string) $profile->base_currency));
        ProfileMember::query()->forceCreate([
            'profile_id' => $profile->getKey(),
            'user_id' => $viewer->getKey(),
            'role' => MemberRole::Viewer,
            'accepted_at' => now(),
            'revoked_at' => null,
        ]);

        $this->expectException(AuthorizationException::class);
        app(AddRecordGuarantor::class)->handle($viewer, $record, RecordGuarantorData::fromInput(['partyName' => 'Not allowed']));
    }
}
