<?php

namespace App\Actions\Staff;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Models\SupportAccessGrant;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RequestSupportAccess
{
    public function handle(User $requester, SupportCase $case): SupportAccessGrant
    {
        return DB::transaction(function () use ($requester, $case): SupportAccessGrant {
            $lockedCase = SupportCase::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($requester)->authorize('request', [SupportAccessGrant::class, $lockedCase]);

            $existingRequest = $lockedCase->grants()
                ->whereIn('status', [SupportAccessGrantStatus::Pending->value, SupportAccessGrantStatus::Approved->value])
                ->where(function (Builder $query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereNull('revoked_at')
                ->exists();

            if ($existingRequest) {
                throw ValidationException::withMessages(['case' => 'An active or pending support access request already exists.']);
            }

            $grant = $lockedCase->grants()->create([
                'requested_by' => $requester->getKey(),
                'status' => SupportAccessGrantStatus::Pending,
            ]);

            app(SupportAuditLogger::class)->record($requester, 'support_access.requested', $lockedCase, $lockedCase->profile, $grant, ['status' => 'pending']);

            return $grant;
        });
    }
}
