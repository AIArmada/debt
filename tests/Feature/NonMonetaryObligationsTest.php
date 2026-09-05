<?php

use App\Actions\Documents\UploadDocument;
use App\Actions\Obligations\RecordObligationEvent;
use App\Livewire\Records\Create;
use App\Models\Document;
use App\Models\FinancialProfile;
use App\Models\ObligationEvent;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('obligation category changes with the selected kind', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->assertSet('category', 'personal_loan')
        ->set('obligationKind', 'asset')
        ->assertSet('category', 'borrowed_item')
        ->set('obligationKind', 'service')
        ->assertSet('category', 'personal_help')
        ->set('obligationKind', 'action')
        ->assertSet('category', 'promise');
});

test('countable asset cannot be created with a fractional quantity', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('obligationKind', 'asset')
        ->set('recordTitle', 'Camera quantity validation')
        ->set('obligationTitle', 'Return camera')
        ->set('subjectName', 'Mirrorless camera')
        ->set('subjectQuantity', '0.5')
        ->set('quantityMode', 'countable')
        ->set('subjectUnit', 'camera')
        ->call('preview')
        ->assertHasErrors(['subjectQuantity']);

    $this->assertDatabaseCount('records', 0);
});

test('fractional asset quantity requires a measurable unit', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('obligationKind', 'asset')
        ->set('recordTitle', 'Camera unit validation')
        ->set('obligationTitle', 'Return camera')
        ->set('subjectName', 'Mirrorless camera')
        ->set('subjectQuantity', '0.5')
        ->set('quantityMode', 'measurable')
        ->set('subjectUnit', 'camera')
        ->call('preview')
        ->assertHasErrors(['quantityMode']);

    $this->assertDatabaseCount('records', 0);
});

test('asset obligation tracks additions partial and full returns without currency', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Gold loan arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'precious_item',
        'title' => 'Borrowed gold',
        'status' => 'active',
        'subject_name' => '916 gold bracelet',
        'subject_quantity' => '20.0000',
        'current_subject_quantity' => '20.0000',
        'quantity_mode' => 'measurable',
        'subject_unit' => 'gram',
        'asset_type' => 'physical',
        'data_confidence' => 'verified',
    ]);

    $action = app(RecordObligationEvent::class);
    $additional = $action->handle($user, $obligation, obligationEvent('added', '2.5000'));
    $obligation->refresh();

    expect($additional->quantity_effect)->toBe('increase')
        ->and($obligation->current_subject_quantity)->toBe('22.5000');
    $partial = $action->handle($user, $obligation, obligationEvent('partially_returned', '7.5000'));
    $obligation->refresh();

    expect($partial->quantity)->toBe('7.5000')
        ->and($obligation->current_subject_quantity)->toBe('15.0000')
        ->and($obligation->status)->toBe('active');
    $action->handle($user, $obligation, obligationEvent('returned', '15.0000'));

    expect($obligation->fresh()->status)->toBe('settled')
        ->and($obligation->fresh()->current_subject_quantity)->toBe('0.0000')
        ->and($obligation->fresh()->currency)->toBeNull()
        ->and(ObligationEvent::query()->where('obligation_id', $obligation->id)->count())->toBe(3);
});

test('countable asset cannot record a fractional fulfillment update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Camera movement validation'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Return camera',
        'status' => 'active',
        'subject_name' => 'Mirrorless camera',
        'subject_quantity' => '1.0000',
        'current_subject_quantity' => '1.0000',
        'subject_unit' => 'camera',
        'quantity_mode' => 'countable',
        'asset_type' => 'physical',
        'data_confidence' => 'verified',
    ]);

    try {
        app(RecordObligationEvent::class)->handle($user, $obligation, obligationEvent('partially_returned', '0.5'));
        $this->fail('A countable asset must reject fractional fulfillment quantities.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['quantity'][0])->toBe('Whole-unit items must use a whole number, such as 1 camera or 2 cameras.');
    }

    $this->assertDatabaseCount('obligation_events', 0);
    expect($obligation->fresh()->current_subject_quantity)->toBe('1.0000');
});

test('action obligation has progress and fulfillment history', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Roof repair arrangement'])->obligations()->create([
        'direction' => 'receivable',
        'obligation_kind' => 'action',
        'category' => 'repair_action',
        'title' => 'Roof repair',
        'status' => 'active',
        'completion_criteria' => 'Repair the leaking roof and provide photos.',
        'data_confidence' => 'partial',
    ]);

    $action = app(RecordObligationEvent::class);
    $action->handle($user, $obligation, obligationEvent('progress', null, 'Materials purchased.'));
    $action->handle($user, $obligation, obligationEvent('fulfilled', null, 'Repair completed and accepted.'));

    expect($obligation->fresh()->status)->toBe('settled')
        ->and($obligation->events()->reorder('created_at')->pluck('event_type')->all())->toBe(['progress', 'fulfilled']);
});

test('service obligation tracks time added progress and fulfillment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Repair arrangement'])->obligations()->create([
        'direction' => 'receivable',
        'obligation_kind' => 'service',
        'category' => 'repair_maintenance',
        'title' => 'Repair work owed',
        'status' => 'active',
        'subject_name' => 'Roof repair labour',
        'subject_quantity' => '12.0000',
        'current_subject_quantity' => '12.0000',
        'quantity_mode' => 'measurable',
        'subject_unit' => 'hour',
        'service_type' => 'skilled_work',
        'completion_criteria' => 'Repair the roof and provide photos.',
        'data_confidence' => 'partial',
    ]);

    $action = app(RecordObligationEvent::class);
    $action->handle($user, $obligation, obligationEvent('added', '4.0000'));
    $action->handle($user, $obligation, obligationEvent('progress', '5.0000'));
    $action->handle($user, $obligation, obligationEvent('fulfilled', null, 'Final work accepted.'));

    expect($obligation->fresh()->status)->toBe('settled')
        ->and($obligation->fresh()->current_subject_quantity)->toBe('0.0000')
        ->and($obligation->events()->reorder('created_at')->pluck('event_type')->all())->toBe(['added', 'progress', 'fulfilled']);
});

test('service can be created as a conditional measurable obligation', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('obligationKind', 'service')
        ->set('recordTitle', 'Warranty arrangement')
        ->set('obligationTitle', 'Emergency repair hours')
        ->set('category', 'personal_help')
        ->set('subjectName', 'Emergency repair labour')
        ->set('serviceType', 'skilled_work')
        ->set('subjectQuantity', '8')
        ->set('subjectUnit', 'hour')
        ->set('completionCriteria', 'Complete the repair and confirm the result.')
        ->set('isConditional', true)
        ->set('conditionDescription', 'Only due if the original repair warranty is rejected.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('records.show', Record::query()->firstOrFail()));

    $this->assertDatabaseHas('obligations', [
        'title' => 'Emergency repair hours',
        'obligation_kind' => 'service',
        'subject_quantity' => '8.0000',
        'current_subject_quantity' => '8.0000',
        'is_conditional' => 1,
    ]);
    expect(ObligationEvent::query()->count())->toBe(0);
});

test('event evidence is distinct from general evidence', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Camera arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Borrowed camera',
        'status' => 'active',
        'subject_name' => 'Mirrorless camera',
        'subject_quantity' => '1.0000',
        'current_subject_quantity' => '1.0000',
        'subject_unit' => 'item',
        'asset_type' => 'physical',
        'data_confidence' => 'partial',
    ]);
    $event = app(RecordObligationEvent::class)->handle($user, $obligation, obligationEvent('returned', '1.0000'));

    app(UploadDocument::class)->handle(
        $user,
        $obligation,
        UploadedFile::fake()->create('return-photo.jpg', 100, 'image/jpeg'),
        'receipt',
        'verified',
        null,
        $event,
    );

    $document = Document::query()->firstOrFail();
    expect($obligation->documents()->count())->toBe(0)
        ->and($event->documents()->firstOrFail()->original_filename)->toBe('return-photo.jpg');
    $this->assertDatabaseHas('document_links', [
        'document_id' => $document->id,
        'obligation_id' => null,
        'financial_transaction_id' => null,
        'obligation_event_id' => $event->id,
        'purpose' => 'event_evidence',
    ]);
});

/** @return array{event_type: string, quantity: string|null, occurred_on: string, note: string|null} */
function obligationEvent(string $type, ?string $quantity, ?string $note = null): array
{
    return [
        'event_type' => $type,
        'quantity' => $quantity,
        'occurred_on' => today()->toDateString(),
        'note' => $note,
    ];
}
