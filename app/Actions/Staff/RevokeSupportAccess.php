<?php

namespace App\Actions\Staff;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Models\SupportAccessGrant;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeSupportAccess
{
    public function handle(User $actor, SupportAccessGrant $grant): SupportAccessGrant
    {
        return DB::transaction(function () use ($actor, $grant): SupportAccessGrant {
            $lockedGrant = SupportAccessGrant::query()->whereKey($grant->getKey())->lockForUpdate()->firstOrFail();
            $case = SupportCase::query()->whereKey($lockedGrant->case_id)->lockForUpdate()->firstOrFail();
            $lockedGrant->setRelation('supportCase', $case);
            Gate::forUser($actor)->authorize('revoke', $lockedGrant);

            $lockedGrant->forceFill([
                'status' => SupportAccessGrantStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
            ])->save();
            app(SupportAuditLogger::class)->record($actor, 'support_access.revoked', $case, $case->profile, $lockedGrant, ['status' => 'revoked']);

            return $lockedGrant->refresh();
        });
    }
}
