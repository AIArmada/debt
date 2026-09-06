<?php

namespace App\Jobs;

use App\Domain\Queries\ProfileExport;
use App\Models\FinancialProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

final class GenerateProfileExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $profileId,
        public string $format,
        public string $exportId,
    ) {}

    public function handle(ProfileExport $export): void
    {
        $profile = FinancialProfile::query()->findOrFail($this->profileId);
        $contents = $this->format === 'csv'
            ? $export->toCsv($profile)
            : $export->toJson($profile);
        Storage::disk('private')->put(self::path($this->profileId, $this->exportId, $this->format), $contents);
    }

    public static function path(string $profileId, string $exportId, string $format): string
    {
        return "exports/{$profileId}/{$exportId}.{$format}";
    }
}
