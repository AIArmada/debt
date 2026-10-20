<?php

namespace App\Actions\Promises;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyStatus;
use App\Domain\Queries\RecordVisibilityQuery;
use App\Models\Party;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ArchiveParty
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RecordVisibilityQuery $recordVisibilityQuery,
    ) {}

    public function handle(User $user, Party $party): Party
    {
        $party->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $party);

        return DB::transaction(function () use ($user, $party): Party {
            $locked = Party::query()->whereKey($party->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            $hasOpenObligation = $this->recordVisibilityQuery->forUser($locked->records()->getQuery(), $user)
                ->whereHas('obligations', fn (Builder $query): Builder => $query->where('status', '!=', ObligationStatus::Settled->value))
                ->exists();

            if ($hasOpenObligation) {
                throw ValidationException::withMessages(['party' => 'A person with open promises cannot be archived.']);
            }

            $locked->forceFill(['status' => PartyStatus::Archived])->save();
            $this->activityLogger->record($locked->profile, $user, $locked, 'party_archived', before: ['status' => PartyStatus::Active->value], after: ['status' => PartyStatus::Archived->value]);

            return $locked->refresh();
        });
    }
}
