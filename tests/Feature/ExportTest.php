<?php

use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('authenticated export contains owned data without private storage paths', function () {
    $user = User::factory()->create(['name' => 'Export Owner']);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Home financing arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'housing',
        'title' => 'Home financing',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 125000,
        'data_confidence' => 'verified',
    ]);
    $obligation->transactions()->create([
        'entry_type' => 'payment',
        'balance_effect' => 'decrease',
        'status' => 'confirmed',
        'amount' => 10000,
        'currency' => 'MYR',
        'occurred_on' => '2026-09-03',
    ]);

    $this->actingAs($user)
        ->get(route('data.export'))
        ->assertOk()
        ->assertDownload('debt-management-export-'.now()->format('Y-m-d').'.json')
        ->assertHeader('Content-Type', 'application/json');

    $response = $this->actingAs($user)->get(route('data.export'));
    $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['user']['name'])->toBe('Export Owner')
        ->and($payload['profiles'][0]['records'][0]['obligations'][0]['title'])->toBe('Home financing')
        ->and($payload['profiles'][0]['records'][0]['obligations'][0]['transactions'][0]['amount'])->toBe('100.00')
        ->and($payload['profiles'][0]['records'][0]['obligations'][0]['transactions'][0]['amount_minor'])->toBe(10000)
        ->and($payload['profiles'][0]['records'][0]['obligations'][0]['documents'][0] ?? [])->not->toHaveKey('storage_path');
});

test('export requires authentication', function () {
    $this->get(route('data.export'))->assertRedirect(route('login'));
});
