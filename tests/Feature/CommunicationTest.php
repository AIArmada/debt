<?php

use App\Livewire\Communication\Composer;
use App\Mail\CommunicationMessageMail;
use App\Models\CommunicationMessage;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('user can save a whatsapp draft without sending it', function () {
    Mail::fake();
    $user = User::factory()->create();
    $obligation = createCommunicationObligation($user, 'Aminah');

    Livewire::actingAs($user)
        ->test(Composer::class, ['obligation' => $obligation])
        ->set('channel', 'whatsapp')
        ->set('template', 'collection_reminder')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertSet('whatsappUrl', fn (?string $url): bool => str_starts_with((string) $url, 'https://wa.me/?text='));

    $message = CommunicationMessage::query()->firstOrFail();
    expect($message->status)->toBe('draft')
        ->and($message->thread->channel)->toBe('whatsapp');
    Mail::assertNothingOutgoing();
});

test('user must explicitly send an email and sent message is recorded', function () {
    Mail::fake();
    $user = User::factory()->create();
    $obligation = createCommunicationObligation($user, 'Bank');

    Livewire::actingAs($user)
        ->test(Composer::class, ['obligation' => $obligation])
        ->set('channel', 'email')
        ->set('recipient', 'bank@example.test')
        ->set('body', 'Please confirm the current balance.')
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertSent(CommunicationMessageMail::class);
    $message = CommunicationMessage::query()->firstOrFail();
    expect($message->status)->toBe('sent')
        ->and($message->recipient)->toBe('bank@example.test')
        ->and($message->sent_at)->not->toBeNull();
});

function createCommunicationObligation(User $user, string $title): Obligation
{
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    return $profile->records()->create(['title' => $title.' arrangement'])->obligations()->create([
        'direction' => 'receivable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => $title,
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 80000,
        'data_confidence' => 'partial',
    ]);
}
