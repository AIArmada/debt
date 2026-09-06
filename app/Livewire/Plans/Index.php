<?php

namespace App\Livewire\Plans;

use App\Actions\Promises\CreateBudgetPeriod;
use App\Actions\Promises\Data\CreateBudgetPeriodData;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Index extends Component
{
    public FinancialProfile $profile;

    public string $startsOn = '';

    public string $endsOn = '';

    public string $incomeMinor = '';

    public string $essentialMinor = '';

    public string $reserveMinor = '';

    public string $currency = '';

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        $this->startsOn = today()->toDateString();
        $this->endsOn = today()->addDays(30)->toDateString();
        $this->currency = (string) $profile->base_currency;
        Gate::authorize('view', $profile);
    }

    public function createPeriod(CreateBudgetPeriod $createBudgetPeriod): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $period = $createBudgetPeriod->handle($user, $this->profile, CreateBudgetPeriodData::fromInput([
            'startsOn' => $this->startsOn,
            'endsOn' => $this->endsOn,
            'incomeMinor' => $this->incomeMinor,
            'essentialMinor' => $this->essentialMinor,
            'reserveMinor' => $this->reserveMinor,
            'currency' => $this->currency,
        ]));
        $this->redirectRoute('plans.show', ['profile' => $this->profile, 'period' => $period], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.plans.index', [
            'periods' => $this->profile->budgetPeriods()->latest('starts_on')->get(),
        ])->layout('layouts.app', ['title' => 'Plans']);
    }
}
