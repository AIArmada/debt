<?php

namespace App\Livewire\Obligations;

use App\Models\Obligation;
use App\Models\ObligationDeliveryInstruction;
use App\Models\Party;
use App\Models\PartyAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageDeliveryInstructions extends Component
{
    public Obligation $obligation;

    public ?string $addressId = null;

    public ?string $recipientPartyId = null;

    public string $method = 'delivery';

    public string $label = '';

    public string $instructions = '';

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('manageDelivery', $obligation);
        $this->obligation = $obligation;
    }

    public function save(): void
    {
        Gate::authorize('manageDelivery', $this->obligation);
        $validated = $this->validate([
            'addressId' => ['nullable', 'uuid'],
            'recipientPartyId' => ['nullable', 'uuid'],
            'method' => ['required', Rule::in(['delivery', 'in_person', 'courier', 'pickup_point', 'other'])],
            'label' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $profile = $this->obligation->record->profile;
        $address = $validated['addressId'] === null
            ? null
            : PartyAddress::query()->whereKey($validated['addressId'])->whereHas('party', fn ($query) => $query->where('profile_id', $profile->getKey()))->firstOrFail();
        $recipient = $validated['recipientPartyId'] === null
            ? null
            : Party::query()->whereKey($validated['recipientPartyId'])->where('profile_id', $profile->getKey())->whereNull('archived_at')->firstOrFail();

        if ($address === null && blank($validated['instructions'])) {
            $this->addError('addressId', 'Choose an address or add handover instructions.');

            return;
        }

        ObligationDeliveryInstruction::create([
            'obligation_id' => $this->obligation->getKey(),
            'address_id' => $address?->getKey(),
            'recipient_party_id' => $recipient?->getKey(),
            'created_by_user_id' => Auth::id(),
            'method' => $validated['method'],
            'label' => trim($validated['label']),
            'instructions' => filled($validated['instructions']) ? trim($validated['instructions']) : null,
            'status' => 'active',
            'verification_status' => 'needs_review',
            'shown_snapshot' => [
                'label' => trim($validated['label']),
                'method' => $validated['method'],
                'recipient' => $recipient?->preferred_name,
                'address' => $address?->only(['label', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code']),
                'instructions' => $validated['instructions'] ?: null,
            ],
        ]);

        $this->reset(['addressId', 'recipientPartyId', 'label', 'instructions']);
        session()->flash('delivery-instruction-created', 'Delivery instruction saved. It remains separate from the party address and can be changed without rewriting history.');
    }

    public function archive(string $instructionId): void
    {
        Gate::authorize('manageDelivery', $this->obligation);
        $instruction = $this->obligation->deliveryInstructions()->whereKey($instructionId)->where('status', 'active')->firstOrFail();
        $instruction->update(['status' => 'archived', 'superseded_at' => now()]);
    }

    public function render(): View
    {
        Gate::authorize('manageDelivery', $this->obligation);
        $profile = $this->obligation->record->profile;

        return view('livewire.obligations.manage-delivery-instructions', [
            'addresses' => $profile->parties()->whereNull('archived_at')->with('addresses')->get()->flatMap(fn (Party $party) => $party->addresses->map(fn (PartyAddress $address): array => ['id' => $address->id, 'label' => $party->preferred_name.' · '.($address->label ?: 'Address').' · '.collect([$address->address_line_1, $address->city])->filter()->implode(', ')])),
            'parties' => $profile->parties()->whereNull('archived_at')->orderBy('preferred_name')->get(),
            'instructionsList' => $this->obligation->deliveryInstructions()->with(['address.party', 'recipientParty'])->where('status', 'active')->latest()->get(),
        ]);
    }
}
