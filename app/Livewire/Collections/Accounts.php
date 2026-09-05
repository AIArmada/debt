<?php

namespace App\Livewire\Collections;

use App\Domain\Money\Currency;
use App\Models\FinancialProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Accounts extends Component
{
    public FinancialProfile $profile;

    public string $label = '';

    public string $method = 'bank_account';

    public string $provider = '';

    public string $currency = 'MYR';

    public string $accountHolderName = '';

    public string $accountIdentifier = '';

    public function mount(FinancialProfile $profile): void
    {
        Gate::authorize('manageImports', $profile);
        $this->profile = $profile;
        $this->currency = (string) $profile->base_currency;
    }

    public function save(): void
    {
        Gate::authorize('manageImports', $this->profile);
        $validated = $this->validate([
            'label' => ['required', 'string', 'max:120'],
            'method' => ['required', Rule::in(['bank_account', 'e_wallet', 'payment_provider', 'cash', 'other'])],
            'provider' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'accountHolderName' => ['nullable', 'string', 'max:255'],
            'accountIdentifier' => ['required_unless:method,cash', 'nullable', 'string', 'min:8', 'max:255'],
        ]);
        $identifier = trim((string) ($validated['accountIdentifier'] ?? ''));

        $this->profile->collectionAccounts()->create([
            'created_by_user_id' => Auth::id(),
            'method' => $validated['method'],
            'label' => trim($validated['label']),
            'provider' => filled($validated['provider'] ?? null) ? trim($validated['provider']) : null,
            'currency' => $validated['currency'] ?: null,
            'account_holder_name' => filled($validated['accountHolderName'] ?? null) ? trim($validated['accountHolderName']) : null,
            'account_identifier_encrypted' => $identifier !== '' ? $identifier : null,
            'account_identifier_last4' => $identifier !== '' ? substr($identifier, -4) : null,
            'verification_status' => 'unverified',
            'status' => 'active',
        ]);

        $this->reset(['label', 'provider', 'accountHolderName', 'accountIdentifier']);
        session()->flash('collection-account-created', 'Receiving account saved securely.');
    }

    public function archive(string $accountId): void
    {
        Gate::authorize('manageImports', $this->profile);
        $account = $this->profile->collectionAccounts()->whereKey($accountId)->where('status', 'active')->firstOrFail();
        $account->update(['status' => 'archived', 'superseded_at' => now()]);
    }

    public function render(): View
    {
        Gate::authorize('manageImports', $this->profile);

        return view('livewire.collections.accounts', [
            'accounts' => $this->profile->collectionAccounts()
                ->select(['id', 'profile_id', 'method', 'label', 'provider', 'currency', 'account_identifier_last4', 'status'])
                ->where('status', 'active')
                ->latest()
                ->get(),
            'currencies' => Currency::options(),
        ]);
    }
}
