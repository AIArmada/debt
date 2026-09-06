<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ImportCsvData;
use App\Domain\Enums\MemberRole;
use App\Domain\Money\Currency;
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
        $required = ['occurred_on', 'amount_minor', 'currency', 'description'];
        if ($header === false || array_diff($required, $header) !== []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Use occurred_on, amount_minor, currency, description columns.']);
        }
        $positions = array_flip($header);

        return DB::transaction(function () use ($profile, $hash, $data, $handle, $positions): ImportBatch {
            $batch = ImportBatch::query()->create(['profile_id' => $profile->getKey(), 'source_hash' => $hash, 'filename' => $data->file->getClientOriginalName(), 'row_count' => 0]);
            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || count(array_filter($row, static fn (mixed $value): bool => $value !== null && StringNormalizer::trimmed((string) $value) !== '')) === 0) {
                    continue;
                }
                $amount = filter_var($row[$positions['amount_minor']] ?? null, FILTER_VALIDATE_INT);
                $currency = StringNormalizer::uppercaseTrimmed((string) ($row[$positions['currency']] ?? ''));
                if ($amount === false || $amount <= 0 || ! Currency::isSupported($currency)) {
                    throw ValidationException::withMessages(['file' => 'Every row needs a positive amount and supported currency.']);
                }
                $importRow = $batch->rows()->create([
                    'occurred_on' => Carbon::parse((string) $row[$positions['occurred_on']])->toDateString(),
                    'amount_minor' => $amount,
                    'currency' => $currency,
                    'description' => StringNormalizer::optionalTrimmed($row[$positions['description']] ?? null),
                ]);
                $this->importMatcher->suggest($importRow);
                $count++;
            }
            fclose($handle);
            $batch->forceFill(['row_count' => $count])->save();

            return $batch->load('rows');
        });
    }
}
