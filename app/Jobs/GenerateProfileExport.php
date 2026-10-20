<?php

namespace App\Jobs;

use App\Domain\Queries\ProfileExport;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

final class GenerateProfileExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $profileId,
        public string $userId,
        public string $format,
        public string $exportId,
    ) {}

    public function handle(ProfileExport $export, ProfileAccess $profileAccess): void
    {
        $profile = FinancialProfile::query()->findOrFail($this->profileId);
        $user = User::query()->findOrFail($this->userId);
        $profileAccess->accessibleProfiles($user)->whereKey($profile->getKey())->firstOrFail();
        $contents = $this->format === 'csv'
            ? $export->toCsv($profile, $user)
            : $export->toJson($profile, $user);
        $disk = Storage::disk('private');
        $disk->put(self::path($this->profileId, $this->exportId, $this->format), $contents);
        $disk->put(self::recordIdsPath($this->profileId, $this->exportId), json_encode($this->recordIds($contents), JSON_THROW_ON_ERROR));
    }

    public static function path(string $profileId, string $exportId, string $format): string
    {
        return "exports/{$profileId}/{$exportId}.{$format}";
    }

    public static function recordIdsPath(string $profileId, string $exportId): string
    {
        return "exports/{$profileId}/{$exportId}.records.json";
    }

    /** @return list<string> */
    private function recordIds(string $contents): array
    {
        if ($this->format === 'json') {
            /** @var array{records?: list<array{id: string}>} $payload */
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            return array_map(static fn (array $record): string => $record['id'], $payload['records'] ?? []);
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('The export record manifest could not be opened.');
        }

        fwrite($stream, $contents);
        rewind($stream);
        $recordIds = [];
        while (($row = fgetcsv($stream)) !== false) {
            if (($row[0] ?? null) === 'record' && isset($row[1])) {
                $recordIds[] = (string) $row[1];
            }
        }
        fclose($stream);

        return $recordIds;
    }
}
