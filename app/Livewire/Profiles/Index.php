<?php

namespace App\Livewire\Profiles;

use App\Actions\Profiles\CreateFinancialProfile;
use App\Livewire\Concerns\InteractsWithAccessibleProfiles;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAccessibleProfiles;

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
        $this->clearAccessibleProfilesCache();
        $this->resetForm();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', FinancialProfile::class);
        $profiles = $this->accessibleProfilesCollection();
        $profiles->loadCount(['records as active_records_count' => fn ($query) => $query->where('is_archived', false)]);
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return view('livewire.profiles.index', [
            'profiles' => $profiles,
            'roles' => app(ProfileAccess::class)->rolesFor($user, $profiles),
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
}
