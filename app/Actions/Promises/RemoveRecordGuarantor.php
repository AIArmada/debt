<?php

namespace App\Actions\Promises;

use App\Domain\Enums\PartyRole;
use App\Models\RecordParty;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RemoveRecordGuarantor
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, RecordParty $link): void
    {
        DB::transaction(function () use ($user, $link): void {
            $lockedLink = RecordParty::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();
            $lockedLink->load('record.profile', 'party');
            Gate::forUser($user)->authorize('update', $lockedLink->record);

            if ($lockedLink->role !== PartyRole::Guarantor) {
                throw ValidationException::withMessages(['guarantor' => 'Only guarantors can be removed here.']);
            }

            $this->activityLogger->record(
                $lockedLink->record->profile,
                $user,
                $lockedLink,
                'record_guarantor_removed',
                before: ['party_id' => $lockedLink->party_id, 'party_name' => $lockedLink->party?->display_name],
            );
            $lockedLink->delete();
        });
    }
}
