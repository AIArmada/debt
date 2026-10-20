<?php

namespace App\Livewire\Profiles;

use App\Actions\Promises\Data\UpdateFinancialProfileData;
use App\Actions\Promises\UpdateFinancialProfile;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Settings extends Component
{
    public FinancialProfile $profile;

    public string $name = '';

    public string $timezone = 'UTC';

    public bool $interestFreeMode = true;

    public bool $showHijri = false;

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('update', $profile);
        $this->name = $profile->name;
        $this->timezone = $profile->timezone;
        $this->interestFreeMode = $profile->interest_free_mode;
        $this->showHijri = $profile->show_hijri;
    }

    public function save(UpdateFinancialProfile $updateFinancialProfile): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $this->profile = $updateFinancialProfile->handle(
            $user,
            $this->profile,
            UpdateFinancialProfileData::fromInput([
                'name' => $this->name,
                'timezone' => $this->timezone,
                'interestFreeMode' => $this->interestFreeMode,
                'showHijri' => $this->showHijri,
            ]),
        );
        session()->flash('profile-settings-saved', 'Profile settings saved.');
    }

    public function render(): View
    {
        return view('livewire.profiles.settings', ['timezones' => timezone_identifiers_list()])
            ->layout('layouts.app', ['title' => 'Profile settings']);
    }
}
