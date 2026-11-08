<?php

namespace App\Features\PromiseTracker\Actions\Staff;

use App\Features\PromiseTracker\Domain\Enums\SupportAccessGrantStatus;
use App\Features\PromiseTracker\Models\ProfileInvite;
use App\Features\PromiseTracker\Models\SupportAccessGrant;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevokeProfileInvite
{
    public function handle(User $actor, ProfileInvite $invite, SupportCase $case): ProfileInvite
    {
        if ($invite->profile_id !== $case->profile_id) {
            abort(404);
        }

        return DB::transaction(function () use ($actor, $invite, $case): ProfileInvite {
            $activeGrant = SupportAccessGrant::query()
                ->where('case_id', $case->getKey())
                ->where('status', SupportAccessGrantStatus::Approved->value)
                ->whereNull('revoked_at')
                ->where('starts_at', '<=', now())
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($activeGrant === null) {
                abort(404);
            }

            $lockedCase = SupportCase::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvite = ProfileInvite::query()->whereKey($invite->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedInvite->profile_id !== $lockedCase->profile_id || $lockedCase->activeGrant() === null) {
                abort(404);
            }

            Gate::forUser($actor)->authorize('revokeInvite', [$lockedInvite->profile, $lockedCase]);

            if (! $lockedInvite->isUsable()) {
                throw ValidationException::withMessages(['invite' => 'Only a pending, unexpired invitation can be revoked.']);
            }

            $lockedInvite->forceFill(['revoked_at' => now()])->save();
            app(SupportAuditLogger::class)->record($actor, 'profile_invite.revoked', $lockedCase, $lockedInvite->profile, $lockedInvite, ['invite_id' => (string) $lockedInvite->getKey(), 'result' => 'revoked']);

            return $lockedInvite->refresh();
        });
    }
}
