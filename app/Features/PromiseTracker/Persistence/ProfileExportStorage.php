<?php

namespace App\Features\PromiseTracker\Persistence;

use Illuminate\Support\Facades\Storage;

final class ProfileExportStorage
{
    public static function path(string $profileId, string $exportId, string $format): string
    {
        return "exports/{$profileId}/{$exportId}.{$format}";
    }

    public static function recordIdsPath(string $profileId, string $exportId): string
    {
        return "exports/{$profileId}/{$exportId}.records.json";
    }

    public function put(string $profileId, string $exportId, string $format, string $contents, string $recordIds): void
    {
        $disk = Storage::disk('private');
        $disk->put(self::path($profileId, $exportId, $format), $contents);
        $disk->put(self::recordIdsPath($profileId, $exportId), $recordIds);
    }
}
