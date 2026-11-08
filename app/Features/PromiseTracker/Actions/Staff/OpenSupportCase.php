<?php

namespace App\Features\PromiseTracker\Actions\Staff;

use App\Features\PromiseTracker\Domain\Enums\SupportCaseStatus;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpenSupportCase
{
    public function handle(User $actor, FinancialProfile $profile, string $subject, string $reason): SupportCase
    {
        Gate::forUser($actor)->authorize('create', SupportCase::class);
        Gate::forUser($actor)->authorize('viewSummary', $profile);
        $subject = trim($subject);
        $reason = trim($reason);

        if ($subject === '' || mb_strlen($subject) > 160 || $reason === '' || mb_strlen($reason) > 5000) {
            throw ValidationException::withMessages(['subject' => 'Enter a subject up to 160 characters and a reason up to 5,000 characters.']);
        }

        return DB::transaction(function () use ($actor, $profile, $subject, $reason): SupportCase {
            $case = SupportCase::query()->create([
                'profile_id' => $profile->getKey(),
                'opened_by' => $actor->getKey(),
                'subject' => $subject,
                'reason' => $reason,
                'status' => SupportCaseStatus::Open,
            ]);

            app(SupportAuditLogger::class)->record($actor, 'support_case.opened', $case, $profile, $case);

            return $case;
        });
    }
}
