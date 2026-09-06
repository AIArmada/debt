<?php

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Actions\Promises\ReturnQuantity;
use App\Domain\Enums\Direction;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\ProfileExport;
use App\Domain\Queries\ProfileTotals;
use App\Jobs\GenerateProfileExport;
use App\Models\Attachment;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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
        ->and($payload['totals'])->toBe(app(ProfileTotals::class)->forProfile($profile))
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
    $profile = $user->financialProfiles()->firstOrFail();
    Record::factory()->count(ProfileExport::ROW_CAP + 1)->create(['profile_id' => $profile->getKey()]);
    Queue::fake();

    $response = $this->actingAs($user)->get(route('profile.exports.json', $profile));

    $response->assertStatus(202)->assertJsonPath('status', 'queued');
    Queue::assertPushed(GenerateProfileExport::class, fn (GenerateProfileExport $job): bool => $job->profileId === $profile->getKey() && $job->format === 'json');
});

test('queued export jobs write a downloadable file', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $job = new GenerateProfileExport($profile->getKey(), 'json', 'export-one');

    $job->handle(app(ProfileExport::class));

    Storage::disk('private')->assertExists(GenerateProfileExport::path($profile->getKey(), 'export-one', 'json'));
});
