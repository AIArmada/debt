<?php

namespace App\Livewire\Profiles;

use App\Actions\Profiles\CreateFinancialProfile;
use App\Models\FinancialProfile;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
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

    public function mount(): void
    {
        Gate::authorize('viewAny', FinancialProfile::class);
        $this->name = 'New profile';
    }

    public function save(CreateFinancialProfile $createFinancialProfile): void
    {
        Gate::authorize('create', FinancialProfile::class);
        $validated = $this->validate();

        $profile = $createFinancialProfile->handle(Auth::user(), [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'base_currency' => strtoupper($validated['baseCurrency']),
            'timezone' => $validated['timezone'],
            'locale' => $validated['locale'],
            'is_islamic_mode_enabled' => $validated['isIslamicModeEnabled'],
        ]);

        session()->put('selected_profile_id', $profile->getKey());
        session()->flash('profile-created', 'The financial profile was created.');
        $this->resetForm();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', FinancialProfile::class);

        return view('livewire.profiles.index', [
            'profiles' => $this->profiles()->get(),
            'roles' => $this->profiles()->get()->mapWithKeys(fn (FinancialProfile $profile): array => [$profile->getKey() => app(ProfileAccess::class)->role(Auth::user(), $profile)]),
        ])->layout('layouts.app', ['title' => 'Profiles']);
    }

    private function resetForm(): void
    {
        $this->name = 'New profile';
        $this->type = 'personal';
        $this->baseCurrency = 'MYR';
        $this->timezone = 'Asia/Kuala_Lumpur';
        $this->locale = '';
        $this->isIslamicModeEnabled = false;
    }

    /** @return Builder<FinancialProfile> */
    private function profiles(): Builder
    {
        return app(ProfileAccess::class)->accessibleProfiles(Auth::user());
    }
}
