<?php

namespace App\Livewire\Parties;

use App\Actions\Promises\Data\ResolveCounterpartyData;
use App\Actions\Promises\ResolveCounterparty;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use App\Models\FinancialProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

final class Index extends Component
{
    public FinancialProfile $profile;

    public string $name = '';

    public PartyKind $kind = PartyKind::Individual;

    #[Url]
    public string $statusFilter = PartyStatus::Active->value;

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('manageParties', $this->profile);
    }

    public function save(ResolveCounterparty $resolveCounterparty): void
    {
        $resolveCounterparty->handle($this->profile, ResolveCounterpartyData::fromInput([
            'name' => $this->name,
            'kind' => $this->kind->value,
        ]));
        $this->reset('name');
        session()->flash('party-created', 'Person saved.');
    }

    public function render(): View
    {
        $parties = $this->profile->parties()
            ->when($this->statusFilter === PartyStatus::Archived->value, fn (Builder $query): Builder => $query->where('status', PartyStatus::Archived->value))
            ->when($this->statusFilter === PartyStatus::Active->value, fn (Builder $query): Builder => $query->where('status', PartyStatus::Active->value))
            ->latest()
            ->get();

        return view('livewire.parties.index', [
            'parties' => $parties,
        ])->layout('layouts.app', ['title' => 'People']);
    }
}
