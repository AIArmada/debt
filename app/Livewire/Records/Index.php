<?php

namespace App\Livewire\Records;

use App\Models\FinancialProfile;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public string $direction = 'all';

    public string $kind = 'all';

    public function mount(): void
    {
        $selectedProfileId = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $this->profiles()->whereKey($selectedProfileId)->exists()
            ? $selectedProfileId
            : $this->profiles()->first()?->getKey();
    }

    public function updatedProfileId(): void
    {
        abort_unless($this->profiles()->whereKey($this->profileId)->exists(), 403);
        session()->put('selected_profile_id', $this->profileId);
    }

    public function render(): View
    {
        $profile = $this->profiles()->findOrFail($this->profileId);
        $records = $profile->records()
            ->with(['partyLinks.party', 'obligations'])
            ->where('is_archived', false)
            ->latest()
            ->get()
            ->filter(function ($record): bool {
                return $record->obligations->contains(function ($obligation): bool {
                    return ($this->direction === 'all' || $obligation->hasCurrentDirection($this->direction))
                        && ($this->kind === 'all' || $obligation->obligation_kind === $this->kind);
                });
            })
            ->values();

        return view('livewire.records.index', ['profile' => $profile, 'records' => $records, 'profiles' => $this->profiles()->get()])
            ->layout('layouts.app', ['title' => 'Records']);
    }

    /** @return Builder<FinancialProfile> */
    private function profiles(): Builder
    {
        return app(ProfileAccess::class)->accessibleProfiles(Auth::user());
    }
}
