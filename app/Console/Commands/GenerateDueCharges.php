<?php

namespace App\Console\Commands;

use App\Models\FinancialProfile;
use App\Services\GenerateCharges;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class GenerateDueCharges extends Command
{
    protected $signature = 'charges:generate-due';

    protected $description = 'Generate due charge proposals and eligible automatic charges';

    public function handle(GenerateCharges $generateCharges): int
    {
        $generated = 0;
        $skipped = 0;
        $timezones = FinancialProfile::query()->distinct()->orderBy('timezone')->pluck('timezone');

        foreach ($timezones as $timezone) {
            $today = CarbonImmutable::now((string) $timezone);
            $profiles = FinancialProfile::query()
                ->where('timezone', $timezone)
                ->orderBy('id')
                ->get();

            foreach ($profiles as $profile) {
                $result = $generateCharges->forProfile($profile, $today);
                $generated += $result['generated'];
                $skipped += $result['skipped'];
            }
        }

        $this->info("Generated {$generated} charge movement(s); skipped {$skipped} guarded configuration(s).");

        return self::SUCCESS;
    }
}
