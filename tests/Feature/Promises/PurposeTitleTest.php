<?php

use App\Features\PromiseTracker\Actions\Promises\CreatePromise;
use App\Features\PromiseTracker\Actions\Promises\Data\CreatePromiseData;
use App\Features\PromiseTracker\Domain\Enums\Direction;
use App\Features\PromiseTracker\Domain\Queries\PromiseShowData;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Create as CreatePromiseComponent;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Index as PromisesIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Show as PromiseShow;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('purpose becomes the record and obligation title while the party remains available as identity', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Ali',
        'purpose' => 'Laptop repair',
        'direction' => Direction::Payable->value,
        'amount' => '75.00',
    ], 'MYR'));

    expect($record->title)->toBe('Laptop repair')
        ->and($record->obligations->firstOrFail()->title)->toBe('Laptop repair')
        ->and($record->counterparties()->firstOrFail()->display_name)->toBe('Ali');

    $query = app(PromiseShowData::class)->restartQuery($record);
    expect($query['partyName'])->toBe('Ali')
        ->and($query['partyId'])->toBe($record->counterparties()->firstOrFail()->getKey())
        ->and($query['purpose'])->toBe('Laptop repair')
        ->and($query['currency'])->toBe('MYR');

    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->call('addAnotherLikeThis')
        ->assertRedirect(route('promises.create', ['profile' => $profile] + $query));

    Livewire::actingAs($user)
        ->test(PromisesIndex::class, ['profile' => $profile])
        ->assertSee('Laptop repair')
        ->assertSee('Ali');

    Livewire::withQueryParams($query)
        ->actingAs($user)
        ->test(CreatePromiseComponent::class, ['profile' => $profile])
        ->assertSet('partyName', 'Ali')
        ->assertSet('partyId', $query['partyId'])
        ->assertSet('purpose', 'Laptop repair')
        ->assertSet('currency', 'MYR');
});

test('purpose defaults to the counterparty name when omitted', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Nadia',
        'purpose' => '  ',
        'direction' => Direction::Receivable->value,
        'amount' => '20.00',
    ], 'MYR'));

    expect($record->title)->toBe('Nadia')
        ->and($record->obligations->firstOrFail()->title)->toBe('Nadia');
});
