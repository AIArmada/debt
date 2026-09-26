<?php

namespace App\Domain\Queries;

use App\Domain\Enums\PartyStatus;
use App\Domain\StringNormalizer;
use App\Models\FinancialProfile;
use App\Models\Party;
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
                $query->where('display_name', 'like', "{$term}%");
            })
            ->orderBy('display_name')
            ->limit(6)
            ->get();
    }
}
