<?php

namespace App\Features\PromiseTracker\Commands;

use App\Features\PromiseTracker\Actions\Support\ExpireSupportAccessGrants as ExpireSupportAccessGrantsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('support-access:expire')]
#[Description('Mark expired support access grants as expired')]
class ExpireSupportAccessGrants extends Command
{
    public function handle(ExpireSupportAccessGrantsAction $expireSupportAccessGrants): int
    {
        $expired = $expireSupportAccessGrants->handle();

        $this->info("Expired {$expired} support access grant(s).");

        return self::SUCCESS;
    }
}
