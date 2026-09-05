<?php

namespace App\Livewire\Profiles;

use App\Actions\Profiles\ActivateEmergencyAccess;
use App\Actions\Profiles\InviteProfileMember;
use App\Actions\Profiles\UpdateFinancialProfile;
use App\Models\FinancialProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Edit extends Component
{
    public FinancialProfile $profile;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|in:personal,business,family,custom')]
    public string $type = 'personal';

    #[Validate('required|alpha|size:3')]
    public string $baseCurrency = 'MYR';

    #[Validate('required|timezone')]
    public string $timezone = 'Asia/Kuala_Lumpur';

    #[Validate('nullable|string|max:16')]
    public string $locale = '';

    #[Validate('boolean')]
    public bool $isIslamicModeEnabled = false;

    #[Validate('nullable|email|max:255')]
    public string $inviteEmail = '';

    #[Validate('nullable|in:editor,payment_manager,viewer,heir')]
    public string $inviteRole = 'viewer';

    public ?string $invitationUrl = null;

    public function mount(FinancialProfile $profile): void
    {
        Gate::authorize('update', $profile);
        $this->profile = $profile;
        $this->name = $profile->name;
        $this->type = $profile->type;
        $this->baseCurrency = $profile->base_currency;
        $this->timezone = $profile->timezone;
        $this->locale = $profile->locale ?? '';
        $this->isIslamicModeEnabled = $profile->is_islamic_mode_enabled;
    }

    public function save(UpdateFinancialProfile $updateFinancialProfile): void
    {
        Gate::authorize('update', $this->profile);
        $validated = $this->validate();

        $updateFinancialProfile->handle($this->profile, [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'base_currency' => strtoupper($validated['baseCurrency']),
            'timezone' => $validated['timezone'],
            'locale' => $validated['locale'],
            'is_islamic_mode_enabled' => $validated['isIslamicModeEnabled'],
        ]);

        $this->redirectRoute('financial-profiles.index', navigate: true);
    }

    public function invite(InviteProfileMember $inviteProfileMember): void
    {
        Gate::authorize('inviteMember', $this->profile);
        $validated = $this->validate([
            'inviteEmail' => 'required|email|max:255',
            'inviteRole' => 'required|in:editor,payment_manager,viewer,heir',
        ]);
        $result = $inviteProfileMember->handle(auth()->user(), $this->profile, $validated['inviteEmail'], $validated['inviteRole']);
        $this->invitationUrl = route('invitations.accept', $result['token']);
        $this->reset('inviteEmail');
        session()->flash('invitation-created', 'Invitation created. Share the link with the invited person.');
    }

    public function decideEmergencyAccess(string $requestId, string $decision, ActivateEmergencyAccess $activateEmergencyAccess): void
    {
        Gate::authorize('update', $this->profile);
        abort_unless(in_array($decision, ['activate', 'reject'], true), 422);
        $request = $this->profile->emergencyAccessRequests()->whereKey($requestId)->where('status', 'pending')->firstOrFail();
        try {
            $activateEmergencyAccess->handle(auth()->user(), $this->profile, $request, $decision);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('emergencyAccess', $message);
                }
            }

            return;
        }
        session()->flash('emergency-access-updated', $decision === 'activate' ? 'Emergency access activated for the nominated representative.' : 'Emergency access request rejected.');
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);

        return view('livewire.profiles.edit')
            ->with([
                'members' => $this->profile->members()
                    ->select(['id', 'profile_id', 'user_id', 'role', 'revoked_at'])
                    ->with(['user' => fn ($query) => $query->select(['id', 'email'])])
                    ->whereNull('revoked_at')
                    ->get(),
                'emergencyRequests' => $this->profile->emergencyAccessRequests()
                    ->select(['id', 'profile_id', 'user_id', 'status', 'activate_after'])
                    ->with(['user' => fn ($query) => $query->select(['id', 'name', 'email'])])
                    ->where('status', 'pending')
                    ->latest()
                    ->get(),
            ])
            ->layout('layouts.app', ['title' => 'Edit '.$this->profile->name]);
    }
}
