<?php

namespace App\Livewire\Obligations;

use App\Domain\Money\Currency;
use App\Models\Obligation;
use App\Models\ObligationPaymentInstruction;
use App\Models\Party;
use App\Models\PartyPaymentDestination;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ManagePaymentInstructions extends Component
{
    public Obligation $obligation;

    public ?string $paymentDestinationId = null;

    public ?string $beneficiaryPartyId = null;

    public ?string $payeePartyId = null;

    public string $currency = '';

    public string $reference = '';

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('managePaymentInstructions', $obligation);
        $this->obligation = $obligation;
        $this->currency = $obligation->currency ?? '';
    }

    public function save(): void
    {
        Gate::authorize('managePaymentInstructions', $this->obligation);

        if (! $this->obligation->kind()->isMoney()) {
            throw ValidationException::withMessages(['obligation' => 'Payment instructions can only be recorded for money obligations.']);
        }

        $validated = $this->validate([
            'paymentDestinationId' => ['required', 'uuid'],
            'beneficiaryPartyId' => ['nullable', 'uuid'],
            'payeePartyId' => ['nullable', 'uuid'],
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = $this->obligation->record->profile;
        $destination = PartyPaymentDestination::query()
            ->whereKey($validated['paymentDestinationId'])
            ->where('status', 'active')
            ->whereHas('party', fn ($query) => $query->where('profile_id', $profile->getKey()))
            ->firstOrFail();
        $beneficiary = $validated['beneficiaryPartyId'] === null
            ? null
            : Party::query()->whereKey($validated['beneficiaryPartyId'])->where('profile_id', $profile->getKey())->where('status', 'active')->firstOrFail();
        $payee = $validated['payeePartyId'] === null
            ? null
            : Party::query()->whereKey($validated['payeePartyId'])->where('profile_id', $profile->getKey())->where('status', 'active')->firstOrFail();

        ObligationPaymentInstruction::create([
            'obligation_id' => $this->obligation->getKey(),
            'payment_destination_id' => $destination->getKey(),
            'beneficiary_party_id' => $beneficiary?->getKey(),
            'payee_party_id' => $payee?->getKey(),
            'currency' => $validated['currency'] ?? $this->obligation->currency,
            'reference' => filled($validated['reference']) ? trim($validated['reference']) : null,
            'status' => 'active',
            'shown_snapshot' => [
                'destination_label' => $destination->label,
                'destination_method' => $destination->method,
                'destination_provider' => $destination->provider,
                'destination_masked' => $destination->maskedIdentifier(),
                'destination_currency' => $destination->currency,
                'account_holder' => $destination->account_holder_name,
                'beneficiary' => $beneficiary?->preferred_name,
                'payee' => $payee?->preferred_name,
                'currency' => $validated['currency'] ?? $this->obligation->currency,
                'reference' => $validated['reference'] ?: null,
            ],
        ]);

        $this->reset(['paymentDestinationId', 'beneficiaryPartyId', 'payeePartyId', 'reference']);
        $this->currency = $this->obligation->currency ?? '';
        session()->flash('payment-instruction-created', 'Payment instruction saved. It keeps a snapshot of the destination, so later directory changes do not rewrite history.');
    }

    public function archive(string $instructionId): void
    {
        Gate::authorize('managePaymentInstructions', $this->obligation);
        $instruction = $this->obligation->paymentInstructions()->whereKey($instructionId)->where('status', 'active')->firstOrFail();
        $instruction->update(['status' => 'archived', 'superseded_at' => now()]);
    }

    public function render(): View
    {
        Gate::authorize('managePaymentInstructions', $this->obligation);
        $profile = $this->obligation->record->profile;
        $parties = $profile->parties()
            ->select(['id', 'profile_id', 'preferred_name', 'status'])
            ->where('status', 'active')
            ->with(['paymentDestinations' => fn ($query) => $query->select(['id', 'party_id', 'label', 'method', 'provider', 'currency', 'account_identifier_last4', 'status'])->where('status', 'active')])
            ->orderBy('preferred_name')
            ->get();
        $destinations = $parties->flatMap(fn (Party $party) => $party->paymentDestinations->map(fn (PartyPaymentDestination $destination): array => [
            'id' => $destination->id,
            'label' => $party->preferred_name.' · '.($destination->label ?: 'Destination').' · '.$destination->maskedIdentifier(),
        ]));

        return view('livewire.obligations.manage-payment-instructions', [
            'destinations' => $destinations,
            'parties' => $parties,
            'instructionsList' => $this->obligation->paymentInstructions()
                ->select(['id', 'obligation_id', 'payment_destination_id', 'beneficiary_party_id', 'payee_party_id', 'currency', 'reference', 'status'])
                ->with([
                    'paymentDestination' => fn ($query) => $query->select(['id', 'label', 'method', 'provider']),
                    'beneficiary' => fn ($query) => $query->select(['id', 'preferred_name']),
                    'payee' => fn ($query) => $query->select(['id', 'preferred_name']),
                ])
                ->where('status', 'active')
                ->latest()
                ->get(),
        ]);
    }
}
