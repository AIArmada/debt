<?php

namespace Tests\Feature;

use App\Actions\Promises\Data\ImportCsvData;
use App\Actions\Promises\ImportCsv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CsvImportFormatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_major_amounts_and_supported_date_formats_are_imported(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $majorFile = UploadedFile::fake()->createWithContent('major.csv', implode("\n", [
            'occurred_on,currency,description,amount',
            '17/10/2026,MYR,October purchase,12.34',
            '17 Oct 2026,MYR,Small purchase,0.01',
        ]));

        $majorBatch = app(ImportCsv::class)->handle($user, $profile, ImportCsvData::fromInput(['file' => $majorFile]));
        $rows = $majorBatch->rows()->get();
        $amounts = $rows->pluck('amount_minor', 'description');
        self::assertSame(1234, $amounts->get('October purchase'));
        self::assertSame(1, $amounts->get('Small purchase'));
        self::assertSame(['2026-10-17', '2026-10-17'], $rows->map(static fn ($row): string => $row->occurred_on->toDateString())->all());

        $minorFile = UploadedFile::fake()->createWithContent('minor.csv', implode("\n", [
            'occurred_on,currency,description,amount_minor',
            '2026-10-18,MYR,Existing export row,2500',
        ]));
        $minorBatch = app(ImportCsv::class)->handle($user, $profile, ImportCsvData::fromInput(['file' => $minorFile]));
        self::assertSame(2500, $minorBatch->rows()->firstOrFail()->amount_minor);
    }

    public function test_csv_with_both_amount_headers_is_rejected_as_ambiguous(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $file = UploadedFile::fake()->createWithContent('ambiguous.csv', implode("\n", [
            'occurred_on,currency,description,amount_minor,amount',
            '2026-10-17,MYR,Ambiguous row,100,1.00',
        ]));

        try {
            app(ImportCsv::class)->handle($user, $profile, ImportCsvData::fromInput(['file' => $file]));
            self::fail('A file with both amount columns must be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame(__('promises.csv_header_invalid'), $exception->errors()['file'][0]);
        }
    }

    public function test_invalid_csv_dates_return_a_row_validation_error_without_importing_rows(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $file = UploadedFile::fake()->createWithContent('invalid-date.csv', implode("\n", [
            'occurred_on,currency,description,amount',
            '31/02/2026,MYR,Impossible date,1.00',
        ]));

        try {
            app(ImportCsv::class)->handle($user, $profile, ImportCsvData::fromInput(['file' => $file]));
            self::fail('Invalid dates must be returned as a row validation error.');
        } catch (ValidationException $exception) {
            self::assertSame(__('promises.csv_row_date_invalid', ['row' => 2]), $exception->errors()['file'][0]);
        }

        $this->assertDatabaseCount('import_batches', 0);
    }
}
