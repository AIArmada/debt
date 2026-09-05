<?php

namespace App\Livewire\Parties;

use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\PartyContactRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageContactRoutes extends Component
{
    use WithPagination;

    public FinancialProfile $profile;

    public ?string $partyId = null;

    public ?string $viaPartyId = null;

    public ?string $viaContactId = null;

    public string $relationshipType = 'personal_assistant';

    public string $purpose = 'general';

    public int $priority = 1;

    public bool $isPrimary = false;

    public string $instructions = '';

    public ?string $editingRouteId = null;

    public ?string $viewingRouteId = null;

    public function mount(FinancialProfile $profile): void
    {
        Gate::authorize('manageParties', $profile);
        $this->profile = $profile;
    }

    public function updatedViaPartyId(): void
    {
        $this->viaContactId = null;
    }

    public function save(): void
    {
        Gate::authorize('manageParties', $this->profile);
        $validated = $this->validate([
            'partyId' => ['required', 'uuid'],
            'viaPartyId' => ['required', 'uuid', 'different:partyId'],
            'viaContactId' => ['nullable', 'uuid'],
            'relationshipType' => ['required', Rule::in(['personal_assistant', 'relative', 'representative', 'friend', 'colleague', 'legal_contact', 'emergency_contact', 'other'])],
            'purpose' => ['required', Rule::in(['general', 'payment', 'delivery', 'legal', 'emergency'])],
            'priority' => ['required', 'integer', 'min:1', 'max:99'],
            'isPrimary' => ['boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $party = $this->party($validated['partyId']);
        $viaParty = $this->party($validated['viaPartyId']);
        $viaContact = null;
        if ($validated['viaContactId'] !== null) {
            $viaContact = PartyContact::query()->whereKey($validated['viaContactId'])->where('party_id', $viaParty->getKey())->firstOrFail();
        }

        $attributes = [
            'via_contact_id' => $viaContact?->getKey(),
            'created_by_user_id' => Auth::id(),
            'relationship_type' => $validated['relationshipType'],
            'priority' => $validated['priority'],
            'is_primary' => $validated['isPrimary'],
            'status' => 'active',
            'instructions' => filled($validated['instructions']) ? trim($validated['instructions']) : null,
            'visibility' => 'restricted',
        ];

        $existingRoute = $this->editingRouteId !== null
            ? $this->route($this->editingRouteId)
            : null;
        $duplicate = PartyContactRoute::query()
            ->where('party_id', $party->getKey())
            ->where('via_party_id', $viaParty->getKey())
            ->where('purpose', $validated['purpose'])
            ->when($existingRoute, fn ($query) => $query->whereKeyNot($existingRoute->getKey()))
            ->exists();

        if ($duplicate) {
            $this->addError('purpose', 'A route for this party, intermediary, and purpose already exists. Edit that saved route instead.');

            return;
        }

        if ($validated['isPrimary']) {
            PartyContactRoute::query()
                ->where('party_id', $party->getKey())
                ->where('purpose', $validated['purpose'])
                ->when($existingRoute, fn ($query) => $query->whereKeyNot($existingRoute->getKey()))
                ->update(['is_primary' => false]);
        }

        if ($existingRoute !== null) {
            $existingRoute->update([
                'party_id' => $party->getKey(),
                'via_party_id' => $viaParty->getKey(),
                'purpose' => $validated['purpose'],
                ...$attributes,
            ]);
        } else {
            PartyContactRoute::query()->create([
                'party_id' => $party->getKey(),
                'via_party_id' => $viaParty->getKey(),
                'purpose' => $validated['purpose'],
                ...$attributes,
            ]);
        }

        $wasEditing = $existingRoute !== null;
        $this->resetRouteForm();
        session()->flash($wasEditing ? 'contact-route-updated' : 'contact-route-created', $wasEditing ? 'Contact route updated.' : 'Contact route saved. The intermediary remains a separate party.');
    }

    public function view(string $routeId): void
    {
        Gate::authorize('manageParties', $this->profile);
        $this->route($routeId);
        $this->viewingRouteId = $routeId;
    }

    public function edit(string $routeId): void
    {
        Gate::authorize('manageParties', $this->profile);
        $route = $this->route($routeId);
        $this->editingRouteId = $route->getKey();
        $this->viewingRouteId = null;
        $this->partyId = $route->party_id;
        $this->viaPartyId = $route->via_party_id;
        $this->viaContactId = $route->via_contact_id;
        $this->relationshipType = $route->relationship_type;
        $this->purpose = $route->purpose;
        $this->priority = (int) $route->priority;
        $this->isPrimary = (bool) $route->is_primary;
        $this->instructions = (string) ($route->instructions ?? '');

        $this->dispatch('contact-route-editing');
    }

    public function cancelEdit(): void
    {
        Gate::authorize('manageParties', $this->profile);
        $this->resetRouteForm();
    }

    public function remove(string $routeId): void
    {
        Gate::authorize('manageParties', $this->profile);
        PartyContactRoute::query()->whereKey($routeId)->whereHas('party', fn ($query) => $query->where('profile_id', $this->profile->getKey()))->delete();
    }

    public function render(): View
    {
        Gate::authorize('manageParties', $this->profile);
        $parties = $this->profile->parties()
            ->select(['id', 'profile_id', 'preferred_name', 'status'])
            ->where('status', 'active')
            ->with(['contacts' => fn ($query) => $query->select(['id', 'party_id', 'type', 'value'])])
            ->orderBy('preferred_name')
            ->get();
        $routes = PartyContactRoute::query()
            ->select(['id', 'party_id', 'via_party_id', 'via_contact_id', 'relationship_type', 'purpose', 'priority', 'is_primary', 'status', 'instructions'])
            ->whereIn('party_id', $parties->modelKeys())
            ->with([
                'party' => fn ($query) => $query->select(['id', 'preferred_name']),
                'viaParty' => fn ($query) => $query->select(['id', 'preferred_name']),
                'viaContact' => fn ($query) => $query->select(['id', 'type', 'value']),
            ])
            ->where('status', 'active')
            ->orderBy('party_id')
            ->orderBy('priority')
            ->paginate(20);

        return view('livewire.parties.manage-contact-routes', compact('parties', 'routes'));
    }

    private function party(string $id): Party
    {
        return Party::query()
            ->select(['id', 'profile_id', 'preferred_name', 'status'])
            ->whereKey($id)
            ->where('profile_id', $this->profile->getKey())
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function route(string $id): PartyContactRoute
    {
        return PartyContactRoute::query()
            ->select(['id', 'party_id', 'via_party_id', 'via_contact_id', 'relationship_type', 'purpose', 'priority', 'is_primary', 'status', 'instructions'])
            ->whereKey($id)
            ->whereHas('party', fn ($query) => $query->where('profile_id', $this->profile->getKey()))
            ->with([
                'party' => fn ($query) => $query->select(['id', 'preferred_name']),
                'viaParty' => fn ($query) => $query->select(['id', 'preferred_name']),
                'viaContact' => fn ($query) => $query->select(['id', 'type', 'value']),
            ])
            ->firstOrFail();
    }

    private function resetRouteForm(): void
    {
        $this->reset(['partyId', 'viaPartyId', 'viaContactId', 'instructions', 'editingRouteId', 'viewingRouteId']);
        $this->relationshipType = 'personal_assistant';
        $this->purpose = 'general';
        $this->priority = 1;
        $this->isPrimary = false;
    }
}
