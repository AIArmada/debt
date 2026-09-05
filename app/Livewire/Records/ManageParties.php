<?php

namespace App\Livewire\Records;

use App\Models\Party;
use App\Models\Record;
use App\Models\RecordParty;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageParties extends Component
{
    public Record $record;

    public ?string $partyId = null;

    public string $role = 'other_party';

    public string $notes = '';

    public function mount(Record $record): void
    {
        Gate::authorize('view', $record);
        $this->record = $record;
    }

    public function add(): void
    {
        Gate::authorize('update', $this->record);
        $validated = $this->validate([
            'partyId' => ['required', 'uuid'],
            'role' => ['required', Rule::in(['other_party', 'beneficiary', 'obligor', 'guarantor', 'witness', 'representative', 'contact', 'custodian', 'service_recipient'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $party = Party::query()
            ->whereKey($validated['partyId'])
            ->where('profile_id', $this->record->profile_id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($validated['role'] === 'other_party') {
            $this->record->partyLinks()->where('role', 'other_party')->update(['is_primary' => false]);
        }

        RecordParty::query()->updateOrCreate(
            ['record_id' => $this->record->getKey(), 'party_id' => $party->getKey(), 'role' => $validated['role']],
            [
                'created_by_user_id' => Auth::id(),
                'is_primary' => $validated['role'] === 'other_party',
                'responsibility_scope' => 'record',
                'status' => 'active',
                'notes' => $validated['notes'] ?: null,
                'visibility' => $this->record->sensitivity === 'shared' ? 'shared' : 'restricted',
            ],
        );

        $this->reset(['partyId', 'notes']);
        $this->dispatch('party-added');
    }

    public function remove(string $linkId): void
    {
        Gate::authorize('update', $this->record);
        $this->record->partyLinks()->whereKey($linkId)->delete();
        $this->dispatch('party-removed');
    }

    public function render(): View
    {
        Gate::authorize('view', $this->record);
        $record = $this->record->load([
            'partyLinks' => fn ($query) => $query->select(['id', 'record_id', 'party_id', 'role', 'is_primary', 'notes']),
            'partyLinks.party' => fn ($query) => $query->select(['id', 'profile_id', 'preferred_name']),
            'partyLinks.party.contacts' => fn ($query) => $query->select(['id', 'party_id', 'value', 'is_message_safe']),
            'profile',
        ]);
        $parties = $record->profile->parties()
            ->select(['id', 'profile_id', 'kind', 'preferred_name', 'status'])
            ->where('status', 'active')
            ->orderBy('preferred_name')
            ->get();

        return view('livewire.records.manage-parties', compact('record', 'parties'));
    }
}
