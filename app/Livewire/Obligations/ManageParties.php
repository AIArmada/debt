<?php

namespace App\Livewire\Obligations;

use App\Models\Obligation;
use App\Models\ObligationParty;
use App\Models\Party;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageParties extends Component
{
    public Obligation $obligation;

    public ?string $partyId = null;

    public string $role = 'beneficiary';

    public string $shareBasis = 'full';

    public ?string $sharePercent = null;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('view', $obligation);
        $this->obligation = $obligation;
        $this->role = $obligation->direction === 'payable' ? 'beneficiary' : 'obligor';
    }

    public function add(): void
    {
        Gate::authorize('update', $this->obligation);
        $validated = $this->validate([
            'partyId' => ['required', 'uuid'],
            'role' => ['required', Rule::in(['obligor', 'beneficiary', 'guarantor', 'payer', 'payee', 'creditor', 'debtor', 'service_recipient', 'custodian'])],
            'shareBasis' => ['required', Rule::in(['full', 'percentage', 'fixed_amount', 'unspecified'])],
            'sharePercent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);

        if ($validated['shareBasis'] === 'percentage' && blank($validated['sharePercent'])) {
            $this->addError('sharePercent', 'Enter the percentage for this party.');

            return;
        }

        $party = Party::query()
            ->whereKey($validated['partyId'])
            ->where('profile_id', $this->obligation->record->profile_id)
            ->where('status', 'active')
            ->firstOrFail();

        ObligationParty::query()->updateOrCreate(
            ['obligation_id' => $this->obligation->getKey(), 'party_id' => $party->getKey(), 'role' => $validated['role']],
            [
                'created_by_user_id' => Auth::id(),
                'share_basis' => $validated['shareBasis'],
                'share_percent' => $validated['shareBasis'] === 'percentage' ? $validated['sharePercent'] : null,
                'status' => 'active',
            ],
        );

        $this->reset(['partyId', 'sharePercent']);
        $this->dispatch('party-added');
    }

    public function remove(string $linkId): void
    {
        Gate::authorize('update', $this->obligation);
        $this->obligation->partyLinks()->whereKey($linkId)->delete();
        $this->dispatch('party-removed');
    }

    public function render(): View
    {
        Gate::authorize('view', $this->obligation);
        $obligation = $this->obligation->load(['record.profile', 'partyLinks.party']);
        $parties = $obligation->record->profile->parties()
            ->select(['id', 'profile_id', 'preferred_name', 'status'])
            ->where('status', 'active')
            ->orderBy('preferred_name')
            ->get();

        return view('livewire.obligations.manage-parties', compact('obligation', 'parties'));
    }
}
