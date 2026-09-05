<?php

namespace App\Livewire\Integrations;

use App\Actions\Profiles\ConnectIntegration;
use App\Livewire\Concerns\InteractsWithAccessibleProfiles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAccessibleProfiles;

    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    #[Validate('required|in:sandbox,stripe,paypal,wise,paynet')]
    public string $provider = 'sandbox';

    #[Validate('required|in:payment,bank')]
    public string $type = 'payment';

    #[Validate('nullable|string|max:4000')]
    public string $secret = '';

    public function mount(): void
    {
        $profiles = $this->accessibleProfilesCollection();
        $selectedProfileId = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $profiles->contains('id', $selectedProfileId)
            ? $selectedProfileId
            : $profiles->first()?->getKey();
    }

    public function updatedProfileId(): void
    {
        $this->accessibleProfile($this->profileId);
        session()->put('selected_profile_id', $this->profileId);
    }

    public function connect(ConnectIntegration $connectIntegration): void
    {
        try {
            $validated = $this->validate();
            $profile = $this->accessibleProfile($this->profileId);
            $connectIntegration->handle(auth()->user(), $profile, $validated['provider'], $validated['type'], $validated['secret'] ?? '');
            session()->flash('integration-connected', 'The provider connection was saved securely.');
        } finally {
            $this->reset('secret');
        }
    }

    public function render(): View
    {
        $profiles = $this->accessibleProfilesCollection();
        $profile = $this->accessibleProfile($this->profileId);
        Gate::authorize('viewIntegrations', $profile);

        return view('livewire.integrations.index', [
            'profiles' => $profiles,
            'integrations' => $profile->integrations()
                ->select(['id', 'profile_id', 'provider', 'type', 'status', 'metadata', 'last_synced_at'])
                ->latest()
                ->get(),
        ])->layout('layouts.app', ['title' => 'Connections']);
    }
}
