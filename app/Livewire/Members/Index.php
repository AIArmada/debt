<?php

namespace App\Livewire\Members;

use App\Actions\Promises\ChangeMemberRole;
use App\Actions\Promises\Data\ChangeMemberRoleData;
use App\Actions\Promises\Data\InviteMemberData;
use App\Actions\Promises\InviteMember;
use App\Actions\Promises\RemoveMember;
use App\Domain\Enums\MemberRole;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Index extends Component
{
    public FinancialProfile $profile;

    public string $email = '';

    public string $role = MemberRole::Viewer->value;

    public ?string $latestInvitationLink = null;

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('manageMembers', $profile);
    }

    public function invite(InviteMember $inviteMember): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $invite = $inviteMember->handle($user, $this->profile, InviteMemberData::fromInput(['email' => $this->email, 'role' => $this->role]));
        $this->latestInvitationLink = route('invitations.accept', ['token' => $invite->getAttribute('token')]);
        $this->reset(['email']);
    }

    public function changeRole(string $memberId, string $role, ChangeMemberRole $changeMemberRole): void
    {
        $member = $this->profile->members()->whereKey($memberId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $changeMemberRole->handle($user, $member, ChangeMemberRoleData::fromInput(['role' => $role]));
    }

    public function remove(string $memberId, RemoveMember $removeMember): void
    {
        $member = $this->profile->members()->whereKey($memberId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $removeMember->handle($user, $member);
    }

    public function render(): View
    {
        return view('livewire.members.index', ['members' => $this->profile->members()->with('user')->whereNull('revoked_at')->get()])
            ->layout('layouts.app', ['title' => 'Members']);
    }
}
