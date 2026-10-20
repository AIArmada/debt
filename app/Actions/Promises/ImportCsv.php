<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ImportCsvData;
use App\Domain\Enums\MemberRole;
use App\Domain\Money\Currency;
use App\Domain\Money\Money;
use App\Domain\Queries\ImportMatcher;
use App\Domain\StringNormalizer;
use App\Models\FinancialProfile;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ImportCsv
{
    public function __construct(private readonly ProfileAccess $profileAccess, private readonly ImportMatcher $importMatcher) {}

    public function handle(User $user, FinancialProfile $profile, ImportCsvData $data): ImportBatch
    {
        if (! $this->profileAccess->can($user, $profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        $contents = file_get_contents($data->file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'The CSV could not be read.']);
        }
        $hash = hash('sha256', $contents);
        $existing = ImportBatch::query()->where('profile_id', $profile->getKey())->where('source_hash', $hash)->first();
        if ($existing !== null) {
            return $existing->load('rows');
        }
        $handle = fopen($data->file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The CSV could not be opened.']);
        }
        $header = fgetcsv($handle);
        $required = ['occurred_on', 'currency', 'description'];
        $hasMinorAmount = is_array($header) && in_array('amount_minor', $header, true);
        $hasMajorAmount = is_array($header) && in_array('amount', $header, true);
        if ($header === false || array_diff($required, $header) !== [] || $hasMinorAmount === $hasMajorAmount) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => __('promises.csv_header_invalid')]);
        }
        $positions = array_flip($header);

        return DB::transaction(function () use ($user, $profile, $hash, $data, $handle, $positions, $hasMinorAmount): ImportBatch {
            $batch = ImportBatch::query()->create(['profile_id' => $profile->getKey(), 'source_hash' => $hash, 'filename' => $data->file->getClientOriginalName(), 'row_count' => 0]);
            $count = 0;
            $rowNumber = 1;
            try {
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    if ($row === [null] || count(array_filter($row, static fn (mixed $value): bool => $value !== null && StringNormalizer::trimmed((string) $value) !== '')) === 0) {
                        continue;
                    }
                    $currency = StringNormalizer::uppercaseTrimmed((string) ($row[$positions['currency']] ?? ''));
                    if (! Currency::isSupported($currency)) {
                        throw ValidationException::withMessages(['file' => __('promises.csv_row_currency_invalid', ['row' => $rowNumber])]);
                    }
                    if ($hasMinorAmount) {
                        $amount = filter_var($row[$positions['amount_minor']] ?? null, FILTER_VALIDATE_INT);
                        if ($amount === false || $amount <= 0) {
                            throw ValidationException::withMessages(['file' => __('promises.csv_row_amount_invalid', ['row' => $rowNumber])]);
                        }
                    } else {
                        try {
                            $money = Money::parseMajor(trim((string) ($row[$positions['amount']] ?? '')), $currency);
                        } catch (\InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(['file' => __('promises.csv_row_amount_invalid', ['row' => $rowNumber])]);
                        }
                        $amount = $money->amountMinor;
                        if ($amount <= 0) {
                            throw ValidationException::withMessages(['file' => __('promises.csv_row_amount_invalid', ['row' => $rowNumber])]);
                        }
                    }
                    $importRow = $batch->rows()->create([
                        'occurred_on' => $this->parseDate((string) ($row[$positions['occurred_on']] ?? ''), $rowNumber),
                        'amount_minor' => $amount,
                        'currency' => $currency,
                        'description' => StringNormalizer::optionalTrimmed($row[$positions['description']] ?? null),
                    ]);
                    $this->importMatcher->suggest($importRow, $user);
                    $count++;
                }
            } finally {
                fclose($handle);
            }
            $batch->forceFill(['row_count' => $count])->save();

            return $batch->load('rows');
        });
    }

    private function parseDate(string $value, int $rowNumber): string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd M Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            $errors = Carbon::getLastErrors();
            if ($date !== null && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->toDateString();
            }
        }

        throw ValidationException::withMessages(['file' => __('promises.csv_row_date_invalid', ['row' => $rowNumber])]);
    }
}
