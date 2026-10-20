<?php

namespace App\Actions\Staff;

use App\Domain\Enums\SupportCaseStatus;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CloseSupportCase
{
    public function handle(User $actor, SupportCase $case): SupportCase
    {
        return DB::transaction(function () use ($actor, $case): SupportCase {
            $lockedCase = SupportCase::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $lockedCase);

            if (! $lockedCase->isOpen()) {
                throw ValidationException::withMessages(['case' => 'Only an open support case can be closed.']);
            }

            $lockedCase->forceFill(['status' => SupportCaseStatus::Closed, 'closed_at' => now(), 'closed_by' => $actor->getKey()])->save();
            app(SupportAuditLogger::class)->record($actor, 'support_case.closed', $lockedCase, $lockedCase->profile, $lockedCase);

            return $lockedCase->refresh();
        });
    }
}
