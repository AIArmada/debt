<?php

namespace App\Actions\Imports;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Models\BankImport;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportBankStatement
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly ActivityNotifier $activityNotifier) {}

    public function handle(User $user, FinancialProfile $profile, UploadedFile $file, string $currency): BankImport
    {
        Gate::forUser($user)->authorize('manageImports', $profile);
        if (! Currency::isSupported($currency)) {
            throw ValidationException::withMessages(['currency' => 'Choose a supported currency.']);
        }

        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240', new SafeUpload]],
        )->validate();

        $contents = $file->get();
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'The statement could not be read.']);
        }
        // Strip a UTF-8 BOM so exported spreadsheets do not break header matching.
        $contents = ltrim($contents, "\xEF\xBB\xBF");

        $checksum = hash_file('sha256', $file->getRealPath());
        if ($profile->bankImports()->whereHas('media', fn ($query) => $query->whereJsonContains('custom_properties->checksum', $checksum))->exists()) {
            throw ValidationException::withMessages(['file' => 'This statement was already imported. Upload a different file.']);
        }

        $parsed = $this->parseCsv($contents, strtoupper($currency));
        $rows = $parsed['rows'];

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'No usable rows were found in this statement. Check the amount column.']);
        }

        return DB::transaction(function () use ($user, $profile, $file, $currency, $rows, $parsed, $checksum): BankImport {
            $import = $profile->bankImports()->create([
                'uploaded_by_user_id' => $user->getKey(),
                'format' => 'csv',
                'currency' => strtoupper($currency),
                'status' => 'processed',
                'row_count' => count($rows),
                'matched_count' => 0,
                'error_message' => $parsed['skipped'] === 0
                    ? null
                    : $parsed['skipped'].' row'.($parsed['skipped'] === 1 ? ' was' : 's were').' skipped because the amount could not be read.',
            ]);
            $originalFilename = SafeUpload::sanitizedFilename($file);
            $import->addMedia($file)
                ->usingName(pathinfo($originalFilename, PATHINFO_FILENAME))
                ->usingFileName($originalFilename)
                ->withCustomProperties([
                    'original_filename' => $originalFilename,
                    'checksum' => $checksum,
                ])
                ->toMediaCollection(BankImport::MEDIA_COLLECTION);
            $import->rows()->createMany($rows);
            $this->auditLogger->record($profile, $user, BankImport::class, $import->getKey(), 'imported', after: [
                'original_filename' => $import->original_filename,
                'format' => $import->format,
                'currency' => $import->currency,
                'row_count' => $import->row_count,
                'status' => $import->status,
            ]);
            $this->activityNotifier->notifyProfileActivity($profile, 'bank_import_processed', 'Bank statement imported', 'A bank statement is ready for review before any rows are recorded.', context: ['currency' => strtoupper($currency), 'row_count' => count($rows)]);

            return $import->load('rows');
        });
    }

    /** @return array{rows: list<array<string, mixed>>, skipped: int} */
    private function parseCsv(string $contents, string $currency): array
    {
        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The statement could not be read.']);
        }
        fwrite($handle, $contents);
        rewind($handle);

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }
            $headers = array_map(fn ($header): string => $this->normaliseHeader((string) $header), $headers);
            $dateColumn = $this->findColumn($headers, ['date', 'transaction_date', 'occurred_on', 'value_date']);
            $descriptionColumn = $this->findColumn($headers, ['description', 'narrative', 'memo', 'details']);
            $amountColumn = $this->findColumn($headers, ['amount', 'transaction_amount', 'value', 'net_amount']);
            $referenceColumn = $this->findColumn($headers, ['reference', 'transaction_id', 'external_reference', 'id']);

            if ($amountColumn === null) {
                throw ValidationException::withMessages(['file' => 'The CSV needs an amount column.']);
            }

            $parsed = [];
            $skipped = 0;
            $rowNumber = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $values = array_pad($values, count($headers), null);
                $amount = $this->parseAmount((string) ($values[$amountColumn] ?? ''), $currency);
                if ($amount === null || $amount === 0) {
                    $skipped++;

                    continue;
                }
                $date = $dateColumn === null ? null : $this->parseDate($values[$dateColumn] ?? null);
                $description = $descriptionColumn === null ? null : (trim((string) ($values[$descriptionColumn] ?? '')) ?: null);
                $reference = $referenceColumn === null ? null : (trim((string) ($values[$referenceColumn] ?? '')) ?: null);
                $parsed[] = [
                    'row_number' => $rowNumber,
                    'occurred_on' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'currency' => $currency,
                    'suggested_direction' => $amount < 0 ? 'payable' : 'receivable',
                    'external_reference' => $reference,
                    'status' => 'unmatched',
                    // Keep the review record useful without duplicating potentially
                    // sensitive bank metadata that is not needed after parsing.
                    'raw_data' => ['source_row' => $rowNumber],
                ];
            }

            return ['rows' => $parsed, 'skipped' => $skipped];
        } finally {
            fclose($handle);
        }
    }

    private function normaliseHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $header) ?? 'column'));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $names
     */
    private function findColumn(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $index = array_search($name, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function parseAmount(string $value, string $currency): ?int
    {
        $value = trim(str_replace([',', ' ', 'RM', '$', '€', '£', '¥'], '', $value));
        if ($value === '') {
            return null;
        }

        try {
            return MoneyAmount::fromMajor($value, $currency);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $timestamp = strtotime($value);

            return $timestamp === false ? null : date('Y-m-d', $timestamp);
        } catch (\Throwable) {
            return null;
        }
    }
}
