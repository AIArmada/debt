<?php

namespace App\Livewire\ExchangeRates;

use App\Actions\Promises\Data\SetExchangeRateData;
use App\Actions\Promises\SetExchangeRate;
use App\Domain\Queries\ConvertedView;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Index extends Component
{
    public FinancialProfile $profile;

    public string $target = '';

    public string $rate = '';

    public string $ratedOn = '';

    public string $source = 'User supplied';

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        $this->target = (string) $profile->base_currency;
        $this->ratedOn = today()->toDateString();
        Gate::authorize('view', $profile);
    }

    public function saveRate(SetExchangeRate $setExchangeRate): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $setExchangeRate->handle($user, $this->profile, SetExchangeRateData::fromInput([
            'from' => $this->profile->base_currency,
            'to' => $this->target,
            'rate' => $this->rate,
            'ratedOn' => $this->ratedOn,
            'source' => $this->source,
        ]));
        $this->rate = '';
    }

    public function render(ConvertedView $convertedView): View
    {
        return view('livewire.exchange-rates.index', [
            'converted' => $convertedView->forProfile($this->profile, $this->target),
        ])->layout('layouts.app', ['title' => 'Exchange rates']);
    }
}
