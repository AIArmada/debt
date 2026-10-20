<?php

namespace App\Domain\Queries;

use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class RecordVisibilityQuery
{
    /**
     * @param  Builder<Record>  $records
     * @return Builder<Record>
     */
    public function forUser(Builder $records, User $user): Builder
    {
        return $records
            ->whereHas('profile', function (Builder $profile) use ($user): void {
                $profile->where('owner_user_id', $user->getKey())
                    ->orWhereHas('members', function (Builder $members) use ($user): void {
                        $members->where('user_id', $user->getKey())
                            ->whereNotNull('accepted_at')
                            ->whereNull('revoked_at');
                    });
            })
            ->where(function (Builder $query) use ($user): void {
                $query->where('visibility', 'shared')
                    ->orWhere('created_by', $user->getKey())
                    ->orWhereHas('profile', fn (Builder $profile): Builder => $profile->where('owner_user_id', $user->getKey()));
            });
    }
}
