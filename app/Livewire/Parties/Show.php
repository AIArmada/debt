<?php

namespace App\Livewire\Parties;

use App\Actions\Parties\AddPartyContact;
use App\Actions\Parties\AddPartyRelationship;
use App\Actions\Parties\AddPaymentDestination;
use App\Actions\Parties\Data\PartyContactData;
use App\Actions\Parties\Data\PartyRelationshipData;
use App\Actions\Parties\Data\PaymentDestinationData;
use App\Actions\Parties\RemovePartyContact;
use App\Actions\Parties\RemovePartyRelationship;
use App\Actions\Parties\RemovePaymentDestination;
use App\Actions\Parties\RevealPaymentDestination;
use App\Actions\Parties\SetPrimaryContact;
use App\Actions\Parties\VerifyPaymentDestination;
use App\Actions\Promises\ArchiveParty;
use App\Actions\Promises\RestoreParty;
use App\Domain\Enums\PartyRelationshipKind;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\PaymentDestinationKind;
use App\Domain\Presenters\PaymentDestinationPresenter;
use App\Domain\Queries\PartyExposure;
use App\Domain\Queries\PartySearch;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\PartyRelationship;
use App\Models\PaymentDestination;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Show extends Component
{
    public FinancialProfile $profile;

    public Party $party;

    public bool $showContactForm = false;

    public string $contactLabel = '';

    public string $contactValue = '';

    public bool $contactIsPrimary = false;

    public bool $showDestinationForm = false;

    public string $destinationKind = PaymentDestinationKind::BankAccount->value;

    public string $destinationLabel = '';

    public string $destinationDetails = '';

    public bool $showRelationshipForm = false;

    public string $relationshipSearch = '';

    public ?string $relationshipPartyId = null;

    public string $relationshipKind = PartyRelationshipKind::AssistantOf->value;

    private ?string $revealedDestinationId = null;

    private ?string $revealedDestinationValue = null;

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

    public function updatedRelationshipSearch(): void
    {
        $this->relationshipPartyId = null;
    }

    public function selectRelationshipParty(string $partyId): void
    {
        $party = $this->profile->parties()
            ->whereKey($partyId)
            ->where('status', PartyStatus::Active->value)
            ->where('id', '<>', $this->party->getKey())
            ->firstOrFail();

        $this->relationshipSearch = $party->display_name;
        $this->relationshipPartyId = $party->getKey();
    }

    public function addContact(AddPartyContact $addPartyContact): void
    {
        $addPartyContact->handle($this->user(), $this->party, PartyContactData::fromInput([
            'label' => $this->contactLabel,
            'value' => $this->contactValue,
            'isPrimary' => $this->contactIsPrimary,
        ]));
        $this->contactLabel = '';
        $this->contactValue = '';
        $this->contactIsPrimary = false;
        $this->showContactForm = false;
        $this->refreshParty();
    }

    public function removeContact(string $contactId, RemovePartyContact $removePartyContact): void
    {
        $contact = $this->party->contacts()->whereKey($contactId)->firstOrFail();
        $removePartyContact->handle($this->user(), $contact);
        $this->refreshParty();
    }

    public function setPrimaryContact(string $contactId, SetPrimaryContact $setPrimaryContact): void
    {
        $contact = $this->party->contacts()->whereKey($contactId)->firstOrFail();
        $setPrimaryContact->handle($this->user(), $contact);
        $this->refreshParty();
    }

    public function addDestination(AddPaymentDestination $addPaymentDestination): void
    {
        $addPaymentDestination->handle($this->user(), $this->party, PaymentDestinationData::fromInput([
            'kind' => $this->destinationKind,
            'label' => $this->destinationLabel,
            'details' => $this->destinationDetails,
        ]));
        $this->destinationKind = PaymentDestinationKind::BankAccount->value;
        $this->destinationLabel = '';
        $this->destinationDetails = '';
        $this->showDestinationForm = false;
        $this->refreshParty();
    }

    public function verifyDestination(string $destinationId, VerifyPaymentDestination $verifyPaymentDestination): void
    {
        $destination = $this->party->paymentDestinations()->whereKey($destinationId)->firstOrFail();
        $verifyPaymentDestination->handle($this->user(), $destination);
        $this->refreshParty();
    }

    public function removeDestination(string $destinationId, RemovePaymentDestination $removePaymentDestination): void
    {
        $destination = $this->party->paymentDestinations()->whereKey($destinationId)->firstOrFail();
        $removePaymentDestination->handle($this->user(), $destination);
        $this->refreshParty();
    }

    public function revealDestination(string $destinationId, RevealPaymentDestination $revealPaymentDestination): void
    {
        $destination = $this->party->paymentDestinations()->whereKey($destinationId)->firstOrFail();
        $this->revealedDestinationId = $destination->getKey();
        $this->revealedDestinationValue = $revealPaymentDestination->handle($this->user(), $destination);
    }

    public function addRelationship(AddPartyRelationship $addPartyRelationship): void
    {
        if ($this->relationshipPartyId === null) {
            throw ValidationException::withMessages(['relationship' => 'Choose a person from this profile.']);
        }

        $addPartyRelationship->handle($this->user(), $this->party, PartyRelationshipData::fromInput([
            'toPartyId' => $this->relationshipPartyId,
            'kind' => $this->relationshipKind,
        ]));
        $this->relationshipSearch = '';
        $this->relationshipPartyId = null;
        $this->relationshipKind = PartyRelationshipKind::AssistantOf->value;
        $this->showRelationshipForm = false;
        $this->refreshParty();
    }

    public function removeRelationship(string $relationshipId, RemovePartyRelationship $removePartyRelationship): void
    {
        $relationship = PartyRelationship::query()
            ->where('profile_id', $this->profile->getKey())
            ->whereKey($relationshipId)
            ->where(function (Builder $query): void {
                $query->where('from_party_id', $this->party->getKey())
                    ->orWhere('to_party_id', $this->party->getKey());
            })
            ->firstOrFail();
        $removePartyRelationship->handle($this->user(), $relationship);
        $this->refreshParty();
    }

    public function render(PartyExposure $partyExposure, PartySearch $partySearch, PaymentDestinationPresenter $paymentDestinationPresenter): View
    {
        $this->party->load([
            'contacts' => function (HasMany $query): void {
                $query->orderByDesc('is_primary')->orderBy('label')->orderBy('id');
            },
            'paymentDestinations' => function (HasMany $query): void {
                $query->with('verifier')->latest('created_at')->latest('id');
            },
            'relationshipsFrom.toParty',
            'relationshipsTo.fromParty',
        ]);

        $records = $this->party->records()
            ->where('is_archived', false)
            ->with('obligations')
            ->latest()
            ->get();

        $destinations = $this->party->paymentDestinations->map(function (PaymentDestination $destination) use ($paymentDestinationPresenter): array {
            return [
                'id' => $destination->getKey(),
                'kind' => $destination->kind->label(),
                'label' => $destination->label,
                'masked' => $paymentDestinationPresenter->masked($destination),
                'is_verified' => $destination->is_verified,
                'verified_by' => $destination->verifier?->name,
            ];
        });

        $assists = $this->party->relationshipsFrom->map(fn (PartyRelationship $relationship): array => [
            'id' => $relationship->getKey(),
            'from_id' => $relationship->from_party_id,
            'from_name' => $this->party->display_name,
            'to_id' => $relationship->to_party_id,
            'to_name' => $relationship->toParty->display_name,
            'kind' => $relationship->kind->label(),
        ]);
        $assistedBy = $this->party->relationshipsTo->map(fn (PartyRelationship $relationship): array => [
            'id' => $relationship->getKey(),
            'from_id' => $relationship->from_party_id,
            'from_name' => $relationship->fromParty->display_name,
            'to_id' => $relationship->to_party_id,
            'to_name' => $this->party->display_name,
            'kind' => $relationship->kind->label(),
        ]);

        $revealedDestinationId = $this->revealedDestinationId;
        $revealedDestinationValue = $this->revealedDestinationValue;
        $this->revealedDestinationId = null;
        $this->revealedDestinationValue = null;

        return view('livewire.parties.show', [
            'records' => $records,
            'exposure' => $partyExposure->forParty($this->party),
            'destinations' => $destinations,
            'assists' => $assists,
            'assistedBy' => $assistedBy,
            'relationshipMatches' => $this->relationshipPartyId === null
                ? $partySearch->forProfile($this->profile, $this->relationshipSearch, $this->party->getKey())
                : new Collection,
            'destinationKinds' => PaymentDestinationKind::cases(),
            'relationshipKinds' => PartyRelationshipKind::cases(),
            'revealedDestinationId' => $revealedDestinationId,
            'revealedDestinationValue' => $revealedDestinationValue,
        ])->layout('layouts.app', ['title' => $this->party->display_name]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function refreshParty(): void
    {
        $this->party->refresh();
    }
}
