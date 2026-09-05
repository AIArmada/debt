<?php

namespace App\Livewire\Profiles;

use App\Actions\Profiles\SetExchangeRate;
use App\Models\FinancialProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class FxRates extends Component
{
    public FinancialProfile $profile;

    #[Validate('required|alpha|size:3')]
    public string $fromCurrency = 'USD';

    #[Validate('required|alpha|size:3|different:fromCurrency')]
    public string $toCurrency = 'MYR';

    #[Validate('required|numeric|gt:0')]
    public string $rate = '';

    #[Validate('required|date')]
    public string $effectiveOn = '';

    public function mount(FinancialProfile $profile): void
    {
        Gate::authorize('update', $profile);
        $this->profile = $profile;
        $this->toCurrency = $profile->base_currency;
        $this->effectiveOn = today()->toDateString();
    }

    public function save(SetExchangeRate $setExchangeRate): void
    {
        Gate::authorize('update', $this->profile);
        $validated = $this->validate();
        $setExchangeRate->handle(auth()->user(), $this->profile, $validated['fromCurrency'], $validated['toCurrency'], $validated['rate'], $validated['effectiveOn']);
        $this->reset('rate');
        session()->flash('fx-rate-saved', 'The exchange rate was saved for this profile.');
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);

        return view('livewire.profiles.fx-rates', ['rates' => $this->profile->exchangeRates()->latest('effective_on')->limit(8)->get()]);
    }
}
