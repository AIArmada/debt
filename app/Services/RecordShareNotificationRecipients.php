<?php

namespace App\Services;

use App\Domain\Enums\NotificationType;
use App\Models\ProfileMember;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

final class RecordShareNotificationRecipients
{
    /** @return Collection<int, User> */
    public function forRecord(Record $record, NotificationType $type): Collection
    {
        $record->loadMissing('profile.members');
        $profile = $record->profile;
        $recipientIds = $profile->members
            ->filter(static fn (ProfileMember $member): bool => $member->accepted_at !== null && $member->revoked_at === null)
            ->pluck('user_id')
            ->push($profile->owner_user_id)
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $recipientIds)
            ->whereDoesntHave('notificationPreferences', function (Builder $query) use ($type): void {
                $query->where('type', $type->value)->where('enabled', false);
            })
            ->get()
            ->filter(fn (User $user): bool => Gate::forUser($user)->allows('view', $record))
            ->values();
    }
}
