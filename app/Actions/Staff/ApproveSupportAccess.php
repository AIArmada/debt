<?php

namespace App\Actions\Staff;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Models\SupportAccessGrant;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveSupportAccess
{
    public function handle(User $approver, SupportAccessGrant $grant, int $durationMinutes = 60): SupportAccessGrant
    {
        if (! in_array($durationMinutes, range(1, 60), true)) {
            throw ValidationException::withMessages(['durationMinutes' => 'Support access can last at most 60 minutes.']);
        }

        return DB::transaction(function () use ($approver, $grant, $durationMinutes): SupportAccessGrant {
            $lockedGrant = SupportAccessGrant::query()->whereKey($grant->getKey())->lockForUpdate()->firstOrFail();
            $case = SupportCase::query()->whereKey($lockedGrant->case_id)->lockForUpdate()->firstOrFail();
            $lockedGrant->setRelation('supportCase', $case);

            if ($lockedGrant->requested_by === $approver->getKey()) {
                throw ValidationException::withMessages(['grant' => 'The requester cannot approve their own support access request.']);
            }

            Gate::forUser($approver)->authorize('approve', $lockedGrant);

            $lockedGrant->forceFill([
                'approved_by' => $approver->getKey(),
                'status' => SupportAccessGrantStatus::Approved,
                'starts_at' => now(),
                'expires_at' => now()->addMinutes($durationMinutes),
            ])->save();
            app(SupportAuditLogger::class)->record($approver, 'support_access.approved', $case, $case->profile, $lockedGrant, ['status' => 'approved']);

            return $lockedGrant->refresh();
        });
    }
}
