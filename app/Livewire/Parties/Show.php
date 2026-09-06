<?php

namespace App\Livewire\Parties;

use App\Actions\Promises\ArchiveParty;
use App\Actions\Promises\RestoreParty;
use App\Domain\Queries\PartyExposure;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Show extends Component
{
    public FinancialProfile $profile;

    public Party $party;

    public function mount(FinancialProfile $profile, Party $party): void
    {
        $this->profile = $profile;
        $this->party = $party;
        Gate::authorize('view', $party);
    }

    public function archive(ArchiveParty $archiveParty): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->party = $archiveParty->handle($user, $this->party);
    }

    public function restore(RestoreParty $restoreParty): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->party = $restoreParty->handle($user, $this->party);
    }

    public function render(PartyExposure $partyExposure): View
    {
        $records = $this->party->records()
            ->where('is_archived', false)
            ->with('obligations')
            ->latest()
            ->get();

        return view('livewire.parties.show', [
            'records' => $records,
            'exposure' => $partyExposure->forParty($this->party),
        ])->layout('layouts.app', ['title' => $this->party->display_name]);
    }
}
