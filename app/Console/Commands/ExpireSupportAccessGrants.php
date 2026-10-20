<?php

namespace App\Console\Commands;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Models\SupportAccessGrant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('support-access:expire')]
#[Description('Mark expired support access grants as expired')]
class ExpireSupportAccessGrants extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = SupportAccessGrant::query()
            ->where('status', SupportAccessGrantStatus::Approved->value)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => SupportAccessGrantStatus::Expired->value, 'updated_at' => now()]);

        $this->info("Expired {$expired} support access grant(s).");

        return self::SUCCESS;
    }
}
