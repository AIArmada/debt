<?php

namespace App\Features\PromiseTracker\Domain\Queries;

use App\Features\PromiseTracker\Domain\Enums\PartyStatus;
use App\Features\PromiseTracker\Domain\StringNormalizer;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PartySearch
{
    /** @return Collection<int, Party> */
    public function forProfile(FinancialProfile $profile, string $term, ?string $exceptPartyId = null): Collection
    {
        $term = StringNormalizer::trimmed($term);
        if ($term === '') {
            return new Collection;
        }

        return $profile->parties()
            ->select(['id', 'profile_id', 'display_name', 'kind', 'status'])
            ->where('status', PartyStatus::Active->value)
            ->when($exceptPartyId !== null, fn (Builder $query): Builder => $query->where('id', '<>', $exceptPartyId))
            ->where(function (Builder $query) use ($term): void {
                $query->where('display_name', 'ILIKE', "%{$term}%");
            })
            ->orderBy('display_name')
            ->limit(6)
            ->get();
    }
}
