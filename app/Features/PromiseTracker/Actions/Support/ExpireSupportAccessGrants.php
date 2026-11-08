<?php

namespace App\Features\PromiseTracker\Actions\Support;

use App\Features\PromiseTracker\Domain\Enums\SupportAccessGrantStatus;
use App\Features\PromiseTracker\Models\SupportAccessGrant;

final class ExpireSupportAccessGrants
{
    public function handle(): int
    {
        return SupportAccessGrant::query()
            ->where('status', SupportAccessGrantStatus::Approved->value)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => SupportAccessGrantStatus::Expired->value, 'updated_at' => now()]);
    }
}
