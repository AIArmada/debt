<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ChangeMemberRoleData;
use App\Domain\Enums\MemberRole;
use App\Models\ProfileMember;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeMemberRole
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ProfileMember $member, ChangeMemberRoleData $data): ProfileMember
    {
        $member->loadMissing('profile');
        Gate::forUser($user)->authorize('manageMembers', $member->profile);

        if ($member->role === MemberRole::Owner || $data->role === MemberRole::Owner) {
            throw ValidationException::withMessages(['role' => 'The profile owner role cannot be changed.']);
        }

        return DB::transaction(function () use ($user, $member, $data): ProfileMember {
            $locked = ProfileMember::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            $before = $locked->role->value;
            $locked->forceFill(['role' => $data->role])->save();
            $this->activityLogger->record($locked->profile, $user, $locked, 'member_role_changed', before: ['role' => $before], after: ['role' => $data->role->value]);

            return $locked->refresh();
        });
    }
}
