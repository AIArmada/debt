<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\SetObligationSharesData;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetObligationShares
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Obligation $obligation, SetObligationSharesData $data): void
    {
        DB::transaction(function () use ($user, $obligation, $data): void {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedObligation->load('record.profile', 'shares');
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            if ($data->shares !== [] && array_sum(array_column($data->shares, 'shareBps')) !== 10000) {
                throw ValidationException::withMessages(['shares' => 'Shares must add up to exactly 100%.']);
            }

            $partyIds = array_column($data->shares, 'partyId');
            $profilePartyIds = Party::query()
                ->where('profile_id', $lockedObligation->record->profile_id)
                ->whereIn('id', $partyIds)
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            if (count($profilePartyIds) !== count($partyIds)) {
                throw ValidationException::withMessages(['shares' => 'Choose parties from this profile.']);
            }

            $before = $lockedObligation->shares->map(static fn ($share): array => [
                'party_id' => $share->party_id,
                'share_bps' => $share->share_bps,
            ])->values()->all();
            $lockedObligation->shares()->delete();

            foreach ($data->shares as $share) {
                $lockedObligation->shares()->create([
                    'party_id' => $share['partyId'],
                    'share_bps' => $share['shareBps'],
                ]);
            }

            $after = $lockedObligation->shares()->orderBy('party_id')->get()->map(static fn ($share): array => [
                'party_id' => $share->party_id,
                'share_bps' => $share->share_bps,
            ])->values()->all();

            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $lockedObligation,
                'obligation_shares_updated',
                before: ['shares' => $before],
                after: ['shares' => $after],
            );
        });
    }
}
