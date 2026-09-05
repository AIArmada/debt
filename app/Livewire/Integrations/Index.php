<?php

namespace App\Livewire\Integrations;

use App\Actions\Profiles\ConnectIntegration;
use App\Models\FinancialProfile;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
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
        $profiles = $this->profiles();
        $selectedProfileId = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $profiles->whereKey($selectedProfileId)->exists()
            ? $selectedProfileId
            : $profiles->first()?->getKey();
    }

    public function updatedProfileId(): void
    {
        abort_unless($this->profiles()->whereKey($this->profileId)->exists(), 403);
        session()->put('selected_profile_id', $this->profileId);
    }

    public function connect(ConnectIntegration $connectIntegration): void
    {
        $validated = $this->validate();
        $profile = $this->profiles()->findOrFail($this->profileId);
        $connectIntegration->handle(auth()->user(), $profile, $validated['provider'], $validated['type'], $validated['secret'] ?? '');
        $this->reset('secret');
        session()->flash('integration-connected', 'The provider connection was saved securely.');
    }

    public function render(): View
    {
        $profile = $this->profiles()->findOrFail($this->profileId);
        Gate::authorize('viewIntegrations', $profile);

        return view('livewire.integrations.index', ['profiles' => $this->profiles()->get(), 'integrations' => $profile->integrations()->latest()->get()])->layout('layouts.app', ['title' => 'Connections']);
    }

    /** @return Builder<FinancialProfile> */
    private function profiles(): Builder
    {
        return app(ProfileAccess::class)->accessibleProfiles(auth()->user());
    }
}
