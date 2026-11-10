<?php

namespace App\Features\PromiseTracker\EntryPoints\Livewire\Members;

use App\Features\PromiseTracker\Actions\Data\ChangeMemberRoleData;
use App\Features\PromiseTracker\Actions\Data\InviteMemberData;
use App\Features\PromiseTracker\Actions\Profiles\ChangeMemberRole;
use App\Features\PromiseTracker\Actions\Profiles\InviteMember;
use App\Features\PromiseTracker\Actions\Profiles\RemoveMember;
use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\User;
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
