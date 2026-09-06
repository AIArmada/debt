<?php

use App\Actions\Promises\AttachEvidence;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\AttachEvidenceData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\DetachEvidence;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\VoidMovement;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Models\ActivityEntry;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

test('evidence can attach to records and movements without crossing parent scopes', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Evidence person',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
        'note' => null,
    ], 'MYR'));
    $movement = $record->obligations->firstOrFail()->moneyMovements->firstOrFail();

    $recordAttachment = app(AttachEvidence::class)->handle(
        $user,
        $record,
        AttachEvidenceData::fromInput(['category' => AttachmentCategory::Agreement->value], UploadedFile::fake()->create('agreement.pdf', 10, 'application/pdf')),
    );
    $movementAttachment = app(AttachEvidence::class)->handle(
        $user,
        $movement,
        AttachEvidenceData::fromInput([
            'linkUrl' => 'https://example.test/receipt',
            'category' => AttachmentCategory::Receipt->value,
        ]),
    );

    expect($record->attachments()->pluck('id')->all())->toBe([$recordAttachment->id])
        ->and($movement->attachments()->pluck('id')->all())->toBe([$movementAttachment->id])
        ->and($recordAttachment->disk_path)->not->toBeNull()
        ->and($movementAttachment->link_url)->toBe('https://example.test/receipt');
    Storage::disk('private')->assertExists($recordAttachment->disk_path);
});

test('voiding a movement leaves its evidence reachable and detaching soft deletes it', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Receipt person',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $movement = app(RecordMoneyMovement::class)->handle(
        $user,
        $obligation,
        RecordMovementData::settlement(100, 'MYR', today()->toDateString()),
    );
    $attachment = app(AttachEvidence::class)->handle(
        $user,
        $movement,
        AttachEvidenceData::fromInput(['category' => AttachmentCategory::Receipt->value], UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')),
    );

    app(VoidMovement::class)->handle($user, $movement, VoidMovementData::fromInput(['reason' => 'Wrong amount.']));

    expect($movement->fresh()->attachments()->whereKey($attachment)->exists())->toBeTrue();
    app(DetachEvidence::class)->handle($user, $attachment);

    expect(Attachment::withTrashed()->find($attachment->getKey())?->deleted_at)->not->toBeNull();
    Storage::disk('private')->assertMissing($attachment->disk_path);
    expect(ActivityEntry::query()->where('subject_id', $attachment->getKey())->where('action', 'evidence_detached')->exists())->toBeTrue();
});

test('evidence downloads require the signed profile-scoped route', function () {
    Storage::fake('private');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Download record', 'note' => null, 'is_archived' => false]);
    $attachment = app(AttachEvidence::class)->handle(
        $owner,
        $record,
        AttachEvidenceData::fromInput(['category' => AttachmentCategory::Photo->value], UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg')),
    );
    Storage::disk('private')->assertExists($attachment->disk_path);
    $url = URL::temporarySignedRoute('attachments.download', now()->addMinutes(5), [
        'profile' => $profile,
        'attachment' => $attachment,
    ]);

    $this->actingAs($owner)->get($url)->assertOk();
    $this->actingAs($other)->get($url)->assertNotFound();
});

test('evidence rejects unsupported sources and cleans files when attachment persistence fails', function () {
    Storage::fake('private');
    expect(fn () => AttachEvidenceData::fromInput(['category' => AttachmentCategory::Other->value]))
        ->toThrow(ValidationException::class);
    expect(fn () => AttachEvidenceData::fromInput([
        'linkUrl' => 'https://example.test',
        'category' => AttachmentCategory::Other->value,
    ], UploadedFile::fake()->create('both.txt', 1, 'text/plain')))
        ->toThrow(ValidationException::class);

    $user = User::factory()->create();
    $record = $user->financialProfiles()->firstOrFail()->records()->create([
        'title' => 'Rollback record',
        'note' => null,
        'is_archived' => false,
    ]);
    DB::listen(function (QueryExecuted $query): void {
        if (str_contains(strtolower($query->sql), 'insert into "attachments"')) {
            throw new RuntimeException('forced attachment persistence failure');
        }
    });

    try {
        expect(fn () => app(AttachEvidence::class)->handle(
            $user,
            $record,
            AttachEvidenceData::fromInput(['category' => AttachmentCategory::Other->value], UploadedFile::fake()->create('rollback.txt', 1, 'text/plain')),
        ))->toThrow(RuntimeException::class, 'forced attachment persistence failure');
    } finally {
        DB::getEventDispatcher()->forget(QueryExecuted::class);
    }
    expect(Storage::disk('private')->allFiles())->toBeEmpty();
});

test('evidence enforces the allowed file types and ten megabyte limit', function () {
    expect(fn () => AttachEvidenceData::fromInput(
        ['category' => AttachmentCategory::Other->value],
        UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
    ))->toThrow(ValidationException::class);

    expect(fn () => AttachEvidenceData::fromInput(
        ['category' => AttachmentCategory::Other->value],
        UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf'),
    ))->toThrow(ValidationException::class);
});

test('a viewer cannot attach evidence', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $profile->members()->forceCreate([
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
    ]);
    $record = $profile->records()->create(['title' => 'Read only', 'note' => null, 'is_archived' => false]);

    expect(fn () => app(AttachEvidence::class)->handle(
        $viewer,
        $record,
        AttachEvidenceData::fromInput(['category' => AttachmentCategory::Other->value], UploadedFile::fake()->create('read-only.txt', 1, 'text/plain')),
    ))->toThrow(AuthorizationException::class);
});
