<?php

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Domain\Enums\Direction;
use App\Domain\Enums\SubjectType;
use App\Livewire\Promises\Show as PromiseShow;
use App\Models\MoneyMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('duplicate settle submits create one settlement movement', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Settle once',
        'direction' => Direction::Payable->value,
        'subjectType' => SubjectType::Money->value,
        'amount' => '50.00',
    ], (string) $profile->base_currency));
    $this->actingAs($user);
    $initialMovementCount = MoneyMovement::query()->where('obligation_id', $record->obligations()->firstOrFail()->getKey())->count();

    Livewire::test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->call('markSettled')
        ->call('markSettled');

    expect(MoneyMovement::query()->where('obligation_id', $record->obligations()->firstOrFail()->getKey())->count())
        ->toBe($initialMovementCount + 1);
});

test('duplicate partial submits create one partial movement', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Partial once',
        'direction' => Direction::Payable->value,
        'subjectType' => SubjectType::Money->value,
        'amount' => '50.00',
    ], (string) $profile->base_currency));
    $this->actingAs($user);
    $initialMovementCount = MoneyMovement::query()->where('obligation_id', $record->obligations()->firstOrFail()->getKey())->count();

    Livewire::test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->set('partialAmount', '20.00')
        ->call('recordPartial')
        ->call('recordPartial')
        ->assertHasErrors('amount');

    expect(MoneyMovement::query()->where('obligation_id', $record->obligations()->firstOrFail()->getKey())->count())
        ->toBe($initialMovementCount + 1);
});

test('destructive write controls have confirmation and loading protection', function () {
    $sources = collect(File::allFiles(resource_path('views/livewire')))
        ->map(fn ($file): string => File::get($file->getPathname()))
        ->implode("\n");

    foreach (['detachEvidence', 'delete', 'revoke', 'restore', 'confirm'] as $action) {
        expect($sources)->toContain('wire:click="'.$action)->and($sources)->toContain('wire:confirm=');
    }

    foreach ([
        'markSettled' => 'wire:click="markSettled',
        'recordPartial' => 'wire:submit="recordPartial',
        'voidMovement' => 'wire:click="voidMovement',
        'saveEvidence' => 'wire:submit="saveEvidence',
        'saveNote' => 'wire:submit="saveNote',
        'archive' => 'wire:click="archive',
        'restore' => 'wire:click="restore',
        'detachEvidence' => 'wire:click="detachEvidence',
        'delete' => 'wire:click="delete',
        'revoke' => 'wire:click="revoke',
        'confirm' => 'wire:click="confirm',
        'generate' => 'wire:click="generate',
        'createToken' => 'wire:submit="createToken',
    ] as $action => $control) {
        expect($sources)->toContain($control)->and($sources)->toContain('wire:loading.attr="disabled"');
    }
});

test('empty home gives the concise onboarding copy', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $this->actingAs($user)
        ->get(route('promises.index', $profile))
        ->assertOk()
        ->assertSee('A promise records who, the direction, and the amount.')
        ->assertSee('Settle the promise when it is done.');
});
