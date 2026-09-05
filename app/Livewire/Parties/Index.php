<?php

namespace App\Livewire\Parties;

use App\Domain\Money\Currency;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\PartyPaymentDestination;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public string $name = '';

    public string $kind = 'individual';

    public string $legalName = '';

    public string $email = '';

    public string $phone = '';

    public string $addressLine1 = '';

    public string $city = '';

    public string $countryCode = 'MY';

    public ?string $editingPartyId = null;

    public ?string $contactPartyId = null;

    public ?string $editingContactId = null;

    public string $contactType = 'email';

    public string $contactLabel = 'Additional';

    public string $contactValue = '';

    public ?string $paymentPartyId = null;

    public ?string $editingPaymentDestinationId = null;

    public ?string $viewingPaymentDestinationId = null;

    public string $paymentLabel = '';

    public string $paymentMethod = 'bank_account';

    public string $paymentProvider = '';

    public ?string $paymentCurrency = 'MYR';

    public string $paymentAccountHolder = '';

    public string $paymentAccountIdentifier = '';

    public string $paymentReference = '';

    public function mount(): void
    {
        $selected = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selected) && $this->profiles()->whereKey($selected)->exists()
            ? $selected
            : $this->profiles()->first()?->getKey();
    }

    public function updatedProfileId(): void
    {
        abort_unless($this->profiles()->whereKey($this->profileId)->exists(), 403);
        session()->put('selected_profile_id', $this->profileId);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'profileId' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['individual', 'organization', 'group', 'estate_or_trust', 'unidentified'])],
            'legalName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'addressLine1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'countryCode' => ['nullable', 'string', 'size:2'],
        ]);

        $profile = $this->profiles()->findOrFail($validated['profileId']);
        Gate::authorize('manageParties', $profile);

        $party = $profile->parties()->create([
            'created_by_user_id' => Auth::id(),
            'kind' => $validated['kind'],
            'preferred_name' => trim($validated['name']),
            'legal_name' => filled($validated['legalName']) ? trim($validated['legalName']) : null,
            'status' => 'active',
            'verification_status' => 'unverified',
            'source' => 'party_directory',
        ]);

        foreach ([['email', $validated['email'] ?? null], ['phone', $validated['phone'] ?? null]] as [$type, $value]) {
            if (filled($value)) {
                $party->contacts()->create([
                    'created_by_user_id' => Auth::id(),
                    'type' => $type,
                    'label' => 'Primary',
                    'value' => trim($value),
                    'purpose' => $type === 'email' ? 'communication' : 'communication',
                    'is_primary' => true,
                    'is_message_safe' => true,
                    'visibility' => 'restricted',
                ]);
            }
        }

        if (filled($validated['addressLine1']) || filled($validated['city'])) {
            $party->addresses()->create([
                'created_by_user_id' => Auth::id(),
                'label' => 'Primary',
                'address_line_1' => $validated['addressLine1'] ?: null,
                'city' => $validated['city'] ?: null,
                'country_code' => strtoupper($validated['countryCode'] ?: 'MY'),
                'purpose' => 'contact',
                'is_primary' => true,
                'visibility' => 'restricted',
            ]);
        }

        $this->reset(['name', 'legalName', 'email', 'phone', 'addressLine1', 'city']);
        session()->flash('party-created', 'Party added. You can now attach it to one or more records.');
    }

    public function editParty(string $partyId): void
    {
        $party = $this->party($partyId);
        Gate::authorize('manageParties', $party->profile);
        $this->resetContactForm();
        $this->resetPaymentDestinationForm();
        $this->editingPartyId = $party->id;
        $this->name = $party->preferred_name;
        $this->kind = $party->kind;
        $this->legalName = $party->legal_name ?? '';
        $emailContact = $party->contacts->first(fn (PartyContact $contact): bool => $contact->type === 'email' && $contact->is_primary)
            ?? $party->contacts->firstWhere('type', 'email');
        $phoneContact = $party->contacts->first(fn (PartyContact $contact): bool => $contact->type === 'phone' && $contact->is_primary)
            ?? $party->contacts->firstWhere('type', 'phone');
        $this->email = (string) ($emailContact?->value ?? '');
        $this->phone = (string) ($phoneContact?->value ?? '');
        $address = $party->addresses->firstWhere('is_primary', true) ?? $party->addresses->first();
        $this->addressLine1 = (string) ($address?->address_line_1 ?? '');
        $this->city = (string) ($address?->city ?? '');
        $this->countryCode = (string) ($address?->country_code ?? 'MY');
    }

    public function updateParty(): void
    {
        $validated = $this->validate([
            'editingPartyId' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['individual', 'organization', 'group', 'estate_or_trust', 'unidentified'])],
            'legalName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'addressLine1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'countryCode' => ['nullable', 'string', 'size:2'],
        ]);
        $party = $this->party($validated['editingPartyId']);
        Gate::authorize('manageParties', $party->profile);
        $party->update([
            'kind' => $validated['kind'],
            'preferred_name' => trim($validated['name']),
            'legal_name' => filled($validated['legalName']) ? trim($validated['legalName']) : null,
        ]);

        foreach ([['email', $validated['email'] ?? null], ['phone', $validated['phone'] ?? null]] as [$type, $value]) {
            $contact = $party->contacts()->where('type', $type)->where('is_primary', true)->first();
            if (filled($value)) {
                $contact?->update(['value' => trim($value)]) ?? $party->contacts()->create([
                    'created_by_user_id' => Auth::id(),
                    'type' => $type,
                    'label' => 'Primary',
                    'value' => trim($value),
                    'purpose' => 'communication',
                    'is_primary' => true,
                    'is_message_safe' => true,
                    'visibility' => 'restricted',
                ]);
            } elseif ($contact !== null) {
                $contact->delete();
            }
        }

        $address = $party->addresses()->where('is_primary', true)->first() ?? $party->addresses()->first();
        if ($address !== null) {
            $address->update([
                'address_line_1' => $validated['addressLine1'] ?: null,
                'city' => $validated['city'] ?: null,
                'country_code' => strtoupper($validated['countryCode'] ?: 'MY'),
            ]);
        } elseif (filled($validated['addressLine1']) || filled($validated['city'])) {
            $party->addresses()->create([
                'created_by_user_id' => Auth::id(),
                'label' => 'Primary',
                'address_line_1' => $validated['addressLine1'] ?: null,
                'city' => $validated['city'] ?: null,
                'country_code' => strtoupper($validated['countryCode'] ?: 'MY'),
                'purpose' => 'contact',
                'is_primary' => true,
                'visibility' => 'restricted',
            ]);
        }

        $this->resetPartyForm();
        session()->flash('party-updated', 'Party details updated. Existing records keep using the same party.');
    }

    public function cancelPartyEdit(): void
    {
        Gate::authorize('manageParties', $this->profiles()->findOrFail($this->profileId));
        $this->resetPartyForm();
    }

    public function addContact(): void
    {
        $validated = $this->validate([
            'contactPartyId' => ['required', 'uuid'],
            'contactType' => ['required', Rule::in(['email', 'phone', 'whatsapp', 'telegram', 'website', 'other'])],
            'contactLabel' => ['required', 'string', 'max:100'],
            'contactValue' => ['required', 'string', 'max:255'],
        ]);
        $party = $this->party($validated['contactPartyId']);
        Gate::authorize('manageParties', $party->profile);
        $attributes = [
            'type' => $validated['contactType'],
            'label' => trim($validated['contactLabel']),
            'value' => trim($validated['contactValue']),
            'purpose' => 'communication',
            'is_message_safe' => in_array($validated['contactType'], ['email', 'phone', 'whatsapp', 'telegram'], true),
            'visibility' => 'restricted',
        ];
        if ($this->editingContactId) {
            $contact = $this->contact($this->editingContactId);
            abort_unless($contact->party_id === $party->id, 403);
            $contact->update($attributes);
            $this->resetContactForm();
            session()->flash('contact-updated', 'Contact detail updated.');

            return;
        }
        $party->contacts()->create($attributes + ['created_by_user_id' => Auth::id()]);
        $this->resetContactForm();
        session()->flash('contact-added', 'Contact detail added.');
    }

    public function addContactFor(string $partyId): void
    {
        $this->contactPartyId = $partyId;
        $this->addContact();
    }

    public function beginContact(string $partyId): void
    {
        $party = $this->party($partyId);
        Gate::authorize('manageParties', $party->profile);
        $this->resetPartyForm();
        $this->resetPaymentDestinationForm();
        $this->resetContactForm();
        $this->contactPartyId = $partyId;
    }

    public function editContact(string $contactId): void
    {
        $contact = $this->contact($contactId);
        Gate::authorize('manageParties', $contact->party->profile);
        $this->resetPartyForm();
        $this->resetPaymentDestinationForm();
        $this->contactPartyId = $contact->party_id;
        $this->editingContactId = $contact->id;
        $this->contactType = $contact->type;
        $this->contactLabel = $contact->label ?? 'Additional';
        $this->contactValue = $contact->value;
    }

    public function cancelContactEdit(): void
    {
        $this->resetContactForm();
    }

    public function archive(string $partyId): void
    {
        $party = $this->party($partyId);
        Gate::authorize('manageParties', $party->profile);
        $party->forceFill(['status' => 'archived', 'archived_at' => now()])->save();
    }

    public function addPaymentDestination(): void
    {
        $validated = $this->validate([
            'paymentPartyId' => ['required', 'uuid'],
            'paymentMethod' => ['required', Rule::in(['bank_account', 'cash', 'e_wallet', 'payment_provider', 'crypto_wallet', 'other'])],
            'paymentLabel' => ['required', 'string', 'max:100'],
            'paymentProvider' => ['nullable', 'string', 'max:100'],
            'paymentCurrency' => ['nullable', Rule::in(Currency::codes())],
            'paymentAccountHolder' => ['nullable', 'string', 'max:255'],
            'paymentAccountIdentifier' => ['required_unless:paymentMethod,cash', 'nullable', 'string', 'max:255'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ]);
        $party = $this->party($validated['paymentPartyId']);
        Gate::authorize('manageParties', $party->profile);
        $identifier = $validated['paymentMethod'] === 'cash'
            ? ''
            : trim((string) ($validated['paymentAccountIdentifier'] ?? ''));
        $attributes = [
            'method' => $validated['paymentMethod'],
            'label' => trim($validated['paymentLabel']),
            'provider' => filled($validated['paymentProvider'] ?? null) ? trim($validated['paymentProvider']) : null,
            'currency' => $validated['paymentCurrency'] ?? null,
            'account_holder_name' => filled($validated['paymentAccountHolder'] ?? null) ? trim($validated['paymentAccountHolder']) : null,
            'account_identifier_encrypted' => $identifier !== '' ? $identifier : null,
            'account_identifier_last4' => $identifier !== '' ? substr($identifier, -4) : null,
            'reference_template' => filled($validated['paymentReference'] ?? null) ? trim($validated['paymentReference']) : null,
            'status' => 'active',
        ];

        if ($this->editingPaymentDestinationId) {
            $destination = $this->paymentDestination($this->editingPaymentDestinationId);
            abort_unless($destination->party_id === $party->getKey(), 403);
            $destination->update($attributes);
            $this->resetPaymentDestinationForm();
            session()->flash('payment-updated', 'Payment destination updated. Existing payment instructions keep their recorded snapshot.');

            return;
        }

        PartyPaymentDestination::create($attributes + [
            'party_id' => $party->getKey(),
            'created_by_user_id' => Auth::id(),
            'verification_status' => 'unverified',
        ]);
        $this->resetPaymentDestinationForm();
        session()->flash('payment-added', 'Payment destination saved securely and shown here in masked form.');
    }

    public function addPaymentDestinationFor(string $partyId): void
    {
        $this->paymentPartyId = $partyId;
        $this->addPaymentDestination();
    }

    public function updatedPaymentMethod(): void
    {
        if ($this->paymentMethod === 'cash') {
            $this->paymentAccountIdentifier = '';
        }
    }

    public function beginPaymentDestination(string $partyId): void
    {
        $party = $this->party($partyId);
        Gate::authorize('manageParties', $party->profile);
        $this->resetPartyForm();
        $this->resetContactForm();
        $this->resetPaymentDestinationForm();
        $this->paymentPartyId = $partyId;
    }

    public function viewPaymentDestination(string $destinationId): void
    {
        $destination = $this->paymentDestination($destinationId);
        Gate::authorize('manageParties', $destination->party->profile);
        $this->viewingPaymentDestinationId = $this->viewingPaymentDestinationId === $destination->id
            ? null
            : $destination->id;
    }

    public function editPaymentDestination(string $destinationId): void
    {
        $destination = $this->paymentDestination($destinationId);
        Gate::authorize('manageParties', $destination->party->profile);
        $this->resetPartyForm();
        $this->resetContactForm();
        $this->paymentPartyId = $destination->party_id;
        $this->editingPaymentDestinationId = $destination->id;
        $this->viewingPaymentDestinationId = null;
        $this->paymentLabel = $destination->label ?? '';
        $this->paymentMethod = $destination->method;
        $this->paymentProvider = $destination->provider ?? '';
        $this->paymentCurrency = $destination->currency;
        $this->paymentAccountHolder = $destination->account_holder_name ?? '';
        $this->paymentAccountIdentifier = $destination->account_identifier_encrypted ?? '';
        $this->paymentReference = $destination->reference_template ?? '';
    }

    public function cancelPaymentDestinationEdit(): void
    {
        $this->resetPaymentDestinationForm();
    }

    public function archivePaymentDestination(string $destinationId): void
    {
        $destination = $this->paymentDestination($destinationId);
        Gate::authorize('manageParties', $destination->party->profile);
        $destination->forceFill(['status' => 'archived', 'superseded_at' => now()])->save();
        if ($this->editingPaymentDestinationId === $destination->id) {
            $this->resetPaymentDestinationForm();
        }
        if ($this->viewingPaymentDestinationId === $destination->id) {
            $this->viewingPaymentDestinationId = null;
        }
        session()->flash('payment-archived', 'Payment destination archived. It will no longer be offered for new payment instructions.');
    }

    public function render(): View
    {
        $profile = $this->profiles()->findOrFail($this->profileId);
        Gate::authorize('view', $profile);
        $canManage = in_array(app(ProfileAccess::class)->role(Auth::user(), $profile), ['owner', 'editor'], true);
        $parties = $profile->parties()
            ->with(['contacts', 'addresses', 'paymentDestinations'])
            ->withCount('recordParties')
            ->whereNull('archived_at')
            ->orderBy('preferred_name')
            ->get();

        $profiles = $this->profiles()->get();

        $currencies = Currency::options();

        return view('livewire.parties.index', compact('profile', 'parties', 'canManage', 'profiles', 'currencies'))
            ->layout('layouts.app', ['title' => 'Parties']);
    }

    /** @return Builder<FinancialProfile> */
    private function profiles(): Builder
    {
        return app(ProfileAccess::class)->accessibleProfiles(Auth::user());
    }

    private function party(string $id): Party
    {
        return Party::query()->whereKey($id)->where('profile_id', $this->profileId)->whereNull('archived_at')->with(['contacts', 'addresses'])->firstOrFail();
    }

    private function contact(string $id): PartyContact
    {
        return PartyContact::query()
            ->whereKey($id)
            ->whereHas('party', fn (Builder $query) => $query->where('profile_id', $this->profileId)->whereNull('archived_at'))
            ->with('party.profile')
            ->firstOrFail();
    }

    private function paymentDestination(string $id): PartyPaymentDestination
    {
        return PartyPaymentDestination::query()
            ->whereKey($id)
            ->whereHas('party', fn (Builder $query) => $query->where('profile_id', $this->profileId)->whereNull('archived_at'))
            ->with('party.profile')
            ->firstOrFail();
    }

    private function resetPaymentDestinationForm(): void
    {
        $this->reset([
            'paymentPartyId',
            'editingPaymentDestinationId',
            'paymentLabel',
            'paymentProvider',
            'paymentAccountHolder',
            'paymentAccountIdentifier',
            'paymentReference',
        ]);
        $this->viewingPaymentDestinationId = null;
        $this->paymentMethod = 'bank_account';
        $this->paymentCurrency = 'MYR';
    }

    private function resetPartyForm(): void
    {
        $this->reset(['editingPartyId', 'name', 'legalName', 'email', 'phone', 'addressLine1', 'city']);
        $this->kind = 'individual';
        $this->countryCode = 'MY';
    }

    private function resetContactForm(): void
    {
        $this->reset(['contactPartyId', 'editingContactId', 'contactValue']);
        $this->contactType = 'email';
        $this->contactLabel = 'Additional';
    }
}
