<?php

use App\Livewire\Obligations\ManageAssets;
use App\Livewire\Obligations\ManageSchedule;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\PaymentSchedule;
use App\Models\PledgedAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('user can create and pause a manual payment schedule', function () {
    $user = User::factory()->create();
    $obligation = createOperationalObligation($user, ['direction' => 'payable']);

    $component = Livewire::actingAs($user)
        ->test(ManageSchedule::class, ['obligation' => $obligation])
        ->set('amount', '150.0000')
        ->set('mode', 'approval_required')
        ->set('frequency', 'monthly')
        ->set('startsOn', '2026-09-01')
        ->set('nextRunsOn', '2026-10-01')
        ->set('perPaymentLimit', '200.0000')
        ->call('save')
        ->assertHasNoErrors();

    $schedule = PaymentSchedule::query()->firstOrFail();
    expect($schedule->mode)->toBe('approval_required')
        ->and($schedule->status)->toBe('active');

    $component->call('pause', $schedule->id)->assertHasNoErrors();
    expect($schedule->fresh()->status)->toBe('paused')
        ->and($schedule->fresh()->paused_at)->not->toBeNull();
});

test('user can record a pledged asset for a pawn obligation', function () {
    $user = User::factory()->create();
    $obligation = createOperationalObligation($user, ['category' => 'pawn_loan']);

    Livewire::actingAs($user)
        ->test(ManageAssets::class, ['obligation' => $obligation])
        ->set('assetType', 'Gold jewellery')
        ->set('description', 'Bracelet')
        ->set('quantity', '12.5000')
        ->set('quantityMode', 'measurable')
        ->set('quantityUnit', 'grams')
        ->set('estimatedValue', '2500.0000')
        ->set('pledgedOn', '2026-09-03')
        ->set('maturesOn', '2027-03-03')
        ->call('save')
        ->assertHasNoErrors();

    $asset = PledgedAsset::query()->firstOrFail();
    expect($asset->asset_type)->toBe('Gold jewellery')
        ->and($asset->quantity_unit)->toBe('grams')
        ->and($asset->obligation_id)->toBe($obligation->id);
});

test('countable pledged asset cannot use a fractional quantity', function () {
    $user = User::factory()->create();
    $obligation = createOperationalObligation($user, ['category' => 'pawn_loan']);

    Livewire::actingAs($user)
        ->test(ManageAssets::class, ['obligation' => $obligation])
        ->set('assetType', 'Camera')
        ->set('quantity', '0.5')
        ->set('quantityMode', 'countable')
        ->set('quantityUnit', 'camera')
        ->call('save')
        ->assertHasErrors(['quantity']);

    $this->assertDatabaseCount('pledged_assets', 0);
});

/** @param array<string, mixed> $overrides */
function createOperationalObligation(User $user, array $overrides = []): Obligation
{
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    return $profile->records()->create(['title' => 'Operational arrangement'])->obligations()->create(array_merge([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Operational record',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 100000,
        'data_confidence' => 'partial',
    ], $overrides));
}
