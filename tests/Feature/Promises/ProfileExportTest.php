<?php

use App\Actions\Promises\AddRecordGuarantor;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordGuarantorData;
use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Actions\Promises\Data\SaveChargeTermsData;
use App\Actions\Promises\ReturnQuantity;
use App\Actions\Promises\SaveChargeTerms;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\ProfileExport;
use App\Domain\Queries\ProfileTotals;
use App\Jobs\GenerateProfileExport;
use App\Models\Attachment;
use App\Models\ProfileMember;
use App\Models\Record;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

pest()->use(RefreshDatabase::class);

test('profile exports include promises, confirmed movements, returns, attachments, and on-screen totals', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $moneyRecord = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Export person',
        'direction' => Direction::Payable->value,
        'subjectType' => SubjectType::Money->value,
        'amount' => '50.00',
    ], (string) $profile->base_currency));
    $quantityRecord = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Export lender',
        'direction' => Direction::Receivable->value,
        'subjectType' => SubjectType::Quantity->value,
        'quantityName' => 'Books',
        'quantityTotal' => '2',
        'quantityUnit' => 'items',
    ], (string) $profile->base_currency));
    app(ReturnQuantity::class)->handle($user, $quantityRecord->obligations->firstOrFail(), RecordQuantityReturnData::fromInput([
        'quantity' => '1',
        'returnedOn' => today()->toDateString(),
        'note' => 'Returned in the export fixture.',
    ]));
    $attachment = Attachment::factory()->create([
        'profile_id' => $profile->getKey(),
        'attachable_id' => $moneyRecord->getKey(),
        'recorded_by' => $user->getKey(),
        'original_name' => 'receipt.pdf',
        'link_url' => 'https://example.com/receipt.pdf',
    ]);

    $this->actingAs($user);
    $jsonResponse = $this->get(route('profile.exports.json', $profile));
    $jsonResponse->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    $payload = $jsonResponse->json();

    expect($payload['profile']['id'])->toBe($profile->getKey())
        ->and($payload['records'])->toHaveCount(2)
        ->and($payload['totals'])->toBe(app(ProfileTotals::class)->forProfile($profile, $user))
        ->and($payload['records'][0]['obligations'][0]['confirmed_movements'])->toHaveCount(1)
        ->and($payload['attachments'][0]['id'])->toBe($attachment->getKey())
        ->and($payload['records'][1]['obligations'][0]['quantity']['returned'])->toBe('1.0000')
        ->and($payload['records'][1]['obligations'][0]['confirmed_returns'])->toHaveCount(1)
        ->and($payload['activity'])->not->toBeEmpty()
        ->and(collect($payload['activity'])->pluck('action')->all())->toContain('promise_created');

    $manifestUrl = $payload['attachments'][0]['url'];
    expect($manifestUrl)->toContain('expires=');
    $this->get($manifestUrl)->assertRedirect($attachment->link_url);

    $csvResponse = $this->get(route('profile.exports.csv', $profile));
    $csvResponse->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertSee('total')
        ->assertSee('5000')
        ->assertSee('receipt.pdf')
        ->assertSee('promise_created');
});

test('an empty profile has a clean export', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $payload = $this->actingAs($user)->get(route('profile.exports.json', $profile))->assertOk()->json();

    expect($payload['records'])->toBe([])
        ->and($payload['attachments'])->toBe([])
        ->and($payload['activity'])->toBe([])
        ->and($payload['totals'])->toBe(['to_pay' => [], 'to_receive' => []]);
});

test('member activity exports exclude private charge terms and guarantor subjects', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $member->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Private terms record',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
    ], 'MYR'));
    $record->forceFill(['visibility' => RecordVisibility::Private])->save();
    $obligation = $record->obligations->firstOrFail();
    $terms = app(SaveChargeTerms::class)->handle($owner, $obligation, SaveChargeTermsData::fromInput([
        'currency' => 'MYR',
        'mode' => 'manual',
        'kind' => 'interest',
        'rate' => '0.12345678',
        'basis' => 'flat',
        'period' => 'monthly',
        'graceDays' => 0,
        'cap' => '',
        'totalPayable' => '',
        'penaltyKind' => 'none',
        'penaltyValue' => '',
        'allocationOrder' => 'charges_first',
        'isFormal' => false,
        'autoApplyOnAcceptance' => false,
        'fixedTotalAgreedUpfront' => false,
    ]));
    $party = $profile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Private guarantor name',
        'status' => PartyStatus::Active,
    ]);
    app(AddRecordGuarantor::class)->handle(
        $owner,
        $record,
        RecordGuarantorData::fromInput(['partyId' => $party->getKey()]),
    );

    $memberActivity = $this->actingAs($member)->get(route('profile.exports.json', $profile))->assertOk()->json('activity');
    $memberActivityJson = json_encode($memberActivity, JSON_THROW_ON_ERROR);
    $ownerActivity = $this->actingAs($owner)->get(route('profile.exports.json', $profile))->assertOk()->json('activity');
    $ownerActivityJson = json_encode($ownerActivity, JSON_THROW_ON_ERROR);

    expect($memberActivityJson)
        ->not->toContain('0.12345678')
        ->not->toContain((string) $terms->terms_hash)
        ->not->toContain('Private guarantor name')
        ->and($ownerActivityJson)
        ->toContain('0.12345678')
        ->toContain((string) $terms->terms_hash)
        ->toContain('Private guarantor name');
});

test('a profile export cannot cross tenancy', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();

    $this->actingAs($other)
        ->get(route('profile.exports.json', $profile))
        ->assertNotFound();
});

test('large exports are queued above the synchronous row cap', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $member->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    Record::factory()->count(ProfileExport::ROW_CAP + 1)->create(['profile_id' => $profile->getKey()]);
    Record::factory()->create([
        'profile_id' => $profile->getKey(),
        'created_by' => $user->getKey(),
        'visibility' => RecordVisibility::Private,
    ]);
    Queue::fake();
    Storage::fake('private');

    $response = $this->actingAs($user)->get(route('profile.exports.json', $profile));

    $response->assertStatus(202)->assertJsonPath('status', 'queued');
    $downloadUrl = (string) $response->json('download_url');
    $exportId = basename((string) parse_url($downloadUrl, PHP_URL_PATH));
    Storage::disk('private')->put(GenerateProfileExport::path($profile->getKey(), $exportId, 'json'), 'private export');
    Storage::disk('private')->put(GenerateProfileExport::recordIdsPath($profile->getKey(), $exportId), '[]');
    $this->actingAs($user)->get($downloadUrl)->assertOk();
    $this->actingAs($user)->get(str_replace('signature=', 'signature=invalid', $downloadUrl))->assertNotFound();
    $this->actingAs($member)->get($downloadUrl)->assertNotFound();
    Queue::assertPushed(GenerateProfileExport::class, fn (GenerateProfileExport $job): bool => $job->profileId === $profile->getKey() && $job->userId === $user->getKey() && $job->format === 'json');
});

test('queued export jobs write a downloadable file', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $job = new GenerateProfileExport($profile->getKey(), $user->getKey(), 'json', 'export-one');

    $job->handle(app(ProfileExport::class), app(ProfileAccess::class));

    Storage::disk('private')->assertExists(GenerateProfileExport::path($profile->getKey(), 'export-one', 'json'));
    Storage::disk('private')->assertExists(GenerateProfileExport::recordIdsPath($profile->getKey(), 'export-one'));
});

test('queued exports are blocked when a shared record becomes private before download', function () {
    Storage::fake('private');
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $editor->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $record = Record::factory()->create(['profile_id' => $profile->getKey(), 'title' => 'Shared at export time']);
    $exportId = 'privacy-recheck';
    $job = new GenerateProfileExport($profile->getKey(), $editor->getKey(), 'json', $exportId);
    $job->handle(app(ProfileExport::class), app(ProfileAccess::class));
    $record->forceFill(['visibility' => RecordVisibility::Private])->save();
    $downloadUrl = URL::temporarySignedRoute('profile.exports.download', now()->addDay(), [
        'profile' => $profile,
        'format' => 'json',
        'export' => $exportId,
        'user_id' => $editor->getKey(),
    ]);

    $this->actingAs($editor)->get($downloadUrl)->assertNotFound();
});
