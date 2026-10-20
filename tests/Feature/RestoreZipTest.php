<?php

namespace Tests\Feature;

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\CreateRecordShare;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\CreateRecordShareData;
use App\Actions\Promises\Data\GenerateInstalmentScheduleData;
use App\Actions\Promises\Data\ImportProfileExportData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\SetObligationSharesData;
use App\Actions\Promises\GenerateInstalmentSchedule;
use App\Actions\Promises\ImportProfileExport;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\SetObligationShares;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\Direction;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\ProfileExport;
use App\Http\Controllers\ProfileExportController;
use App\Models\Attachment;
use App\Models\ObligationChargeTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class RestoreZipTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_restore_preserves_balances_files_instalment_paid_amounts_and_splits(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $firstParty = $profile->parties()->create([
            'kind' => PartyKind::Individual,
            'display_name' => 'First split party',
            'status' => PartyStatus::Active,
        ]);
        $secondParty = $profile->parties()->create([
            'kind' => PartyKind::Individual,
            'display_name' => 'Second split party',
            'status' => PartyStatus::Active,
        ]);
        $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => $firstParty->display_name,
            'partyId' => $firstParty->getKey(),
            'purpose' => 'Restorable promise',
            'direction' => Direction::Payable->value,
            'amount' => '100.01',
        ], (string) $profile->base_currency));
        $obligation = $record->obligations->firstOrFail();
        app(SetObligationShares::class)->handle($user, $obligation, SetObligationSharesData::fromInput([
            'shares' => [
                ['partyId' => $firstParty->getKey(), 'shareBps' => 6000],
                ['partyId' => $secondParty->getKey(), 'shareBps' => 4000],
            ],
        ]));
        app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
            'count' => 2,
            'frequency' => 'weekly',
            'firstDueOn' => today()->addWeek()->toDateString(),
            'payDayOfMonth' => '',
        ]));
        app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::settlement(1000, 'MYR', today()->toDateString()));
        $fileBytes = "receipt bytes\0\xFF";
        $diskPath = 'attachments/source/receipt.bin';
        Storage::disk('private')->put($diskPath, $fileBytes);
        $record->attachments()->create([
            'profile_id' => $profile->getKey(),
            'disk_path' => $diskPath,
            'link_url' => null,
            'original_name' => 'receipt.bin',
            'mime' => 'application/octet-stream',
            'size_bytes' => strlen($fileBytes),
            'category' => AttachmentCategory::Other,
            'recorded_by' => $user->getKey(),
            'created_at' => now(),
        ]);
        ObligationChargeTerm::factory()->create([
            'obligation_id' => $obligation->getKey(),
            'mode' => 'manual',
            'kind' => 'interest',
            'rate' => '0.01000000',
            'basis' => 'reducing_simple',
            'period' => 'monthly',
            'terms_hash' => 'source-terms-hash',
        ]);
        $acknowledgedShare = app(CreateRecordShare::class)->handle(
            $user,
            $record,
            CreateRecordShareData::fromInput([]),
        )['share'];
        $acknowledgedShare->forceFill([
            'acknowledged_at' => now(),
            'acknowledged_name' => 'Counterparty',
        ])->save();
        $disputedShare = app(CreateRecordShare::class)->handle(
            $user,
            $record,
            CreateRecordShareData::fromInput([]),
        )['share'];
        $disputedShare->forceFill([
            'disputed_at' => now(),
            'disputed_name' => 'Counterparty',
            'dispute_note' => 'The date needs review.',
        ])->save();
        $record->forceFill(['is_archived' => true])->save();
        $obligation->forceFill(['status' => ObligationStatus::Paused])->save();
        $sourceBalance = app(OutstandingBalance::class)->forObligation($obligation->fresh());

        $response = $this->actingAs($user)->get(route('profile.exports.zip', $profile));
        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        self::assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $archivePath = $response->baseResponse->getFile()->getPathname();
        $archiveBytes = file_get_contents($archivePath);
        self::assertIsString($archiveBytes);
        @unlink($archivePath);

        $restored = app(ImportProfileExport::class)->handle($user, ImportProfileExportData::fromInput([
            'file' => UploadedFile::fake()->createWithContent('promise-profile.zip', $archiveBytes),
        ]));
        $restoredRecord = $restored->records()->where('title', 'Restorable promise')->firstOrFail();
        $restoredObligation = $restoredRecord->obligations()->firstOrFail();

        self::assertTrue($restoredRecord->is_archived);
        self::assertSame(ObligationStatus::Paused, $restoredObligation->status);
        self::assertSame($sourceBalance, app(OutstandingBalance::class)->forObligation($restoredObligation));
        $restoredShares = $restoredObligation->shares()->with('party')->get()
            ->map(static fn ($share): array => [
                'party' => $share->party->display_name,
                'share_bps' => (int) $share->share_bps,
            ])
            ->sortBy('party')
            ->values()
            ->all();
        self::assertSame([
            ['party' => 'First split party', 'share_bps' => 6000],
            ['party' => 'Second split party', 'share_bps' => 4000],
        ], $restoredShares);
        self::assertSame(1000, (int) $restoredObligation->instalments()->sum('paid_minor'));
        self::assertSame(2, (int) $restoredObligation->instalments()->count());
        self::assertSame(1000, (int) $restoredObligation->instalments()->firstOrFail()->allocations()->sum('amount_minor'));
        self::assertSame('reducing_simple', $restoredObligation->chargeTerms->basis);
        self::assertSame('0.01000000', (string) $restoredObligation->chargeTerms->rate);
        self::assertNotSame('source-terms-hash', $restoredObligation->chargeTerms->terms_hash);
        self::assertNull($restoredObligation->chargeTerms->accepted_at);
        self::assertArrayNotHasKey(
            'record_count',
            $restored->activityEntries()->where('action', 'profile_restored')->firstOrFail()->after,
        );
        self::assertSame(1, (int) $restoredRecord->shares()->where('acknowledged_name', 'Counterparty')->count());
        self::assertSame(1, (int) $restoredRecord->shares()->where('dispute_note', 'The date needs review.')->count());
        $restoredAttachment = Attachment::query()->where('profile_id', $restored->getKey())->firstOrFail();
        self::assertSame($fileBytes, Storage::disk('private')->get((string) $restoredAttachment->disk_path));
    }

    public function test_zip_restore_rejects_unsupported_versions_with_a_clear_error(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'bad-profile-export-');
        self::assertIsString($zipPath);
        $zip = new ZipArchive;
        self::assertSame(true, $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('export.json', '{"version":1}');
        $zip->close();

        try {
            app(ImportProfileExport::class)->handle(User::factory()->create(), ImportProfileExportData::fromInput([
                'file' => new UploadedFile($zipPath, 'unsupported.zip', 'application/zip', UPLOAD_ERR_OK, true),
            ]));
            self::fail('Unsupported export versions must be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame(__('promises.restore_version_unsupported'), $exception->errors()['file'][0]);
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_zip_restore_rejects_manifest_attachments_missing_from_the_archive(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'missing-profile-file-');
        self::assertIsString($zipPath);
        $zip = new ZipArchive;
        self::assertSame(true, $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('export.json', json_encode([
            'version' => 2,
            'profile' => [],
            'parties' => [],
            'records' => [],
            'attachments' => [[
                'id' => '9c2d581c-7f64-4b88-8f2b-3148fba166f2',
                'link_url' => null,
                'original_name' => 'receipt.pdf',
            ]],
        ], JSON_THROW_ON_ERROR));
        $zip->close();

        try {
            app(ImportProfileExport::class)->handle(User::factory()->create(), ImportProfileExportData::fromInput([
                'file' => new UploadedFile($zipPath, 'missing-file.zip', 'application/zip', UPLOAD_ERR_OK, true),
            ]));
            self::fail('A manifest must not refer to an absent attachment entry.');
        } catch (ValidationException $exception) {
            self::assertSame(__('promises.restore_zip_invalid'), $exception->errors()['file'][0]);
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_zip_export_fails_when_a_stored_attachment_file_is_missing(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Missing attachment',
            'direction' => Direction::Payable->value,
            'amount' => '10.00',
        ], (string) $profile->base_currency));
        $record->attachments()->create([
            'profile_id' => $profile->getKey(),
            'disk_path' => 'attachments/missing/receipt.pdf',
            'link_url' => null,
            'original_name' => 'receipt.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 20,
            'category' => AttachmentCategory::Other,
            'recorded_by' => $user->getKey(),
            'created_at' => now(),
        ]);
        $this->actingAs($user);

        try {
            app(ProfileExportController::class)->zip($profile, app(ProfileExport::class));
            self::fail('ZIP export must not silently omit a stored attachment.');
        } catch (RuntimeException $exception) {
            self::assertSame('A stored attachment is missing from private storage.', $exception->getMessage());
        }
    }
}
