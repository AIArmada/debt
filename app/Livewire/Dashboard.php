<?php

namespace App\Livewire;

use App\Domain\Pawn\PawnRiskService;
use App\Domain\Planning\ProfileRepaymentSummary;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public function mount(): void
    {
        $requestedProfileId = request()->query('profile');
        if (is_string($requestedProfileId) && $this->profiles()->whereKey($requestedProfileId)->exists()) {
            $this->profileId = $requestedProfileId;
            session()->put('selected_profile_id', $requestedProfileId);

            return;
        }

        $selectedProfileId = session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $this->profiles()->whereKey($selectedProfileId)->exists()
            ? $selectedProfileId
            : $this->profiles()->first()?->id;

        abort_if($this->profileId === null, 403);
    }

    public function updatedProfileId(): void
    {
        abort_unless($this->profiles()->whereKey($this->profileId)->exists(), 403);
        session()->put('selected_profile_id', $this->profileId);
    }

    public function render(): View
    {
        $profile = $this->profiles()->findOrFail($this->profileId);
        $active = Obligation::query()
            ->whereHas('record', fn ($query) => $query->where('profile_id', $profile->getKey())->where('is_archived', false))
            ->active();

        $activeRecords = (clone $active)->get();
        $reversedObligations = $activeRecords
            ->filter(fn (Obligation $obligation): bool => $obligation->isPositionReversed())
            ->sortBy(fn (Obligation $obligation): string => $obligation->next_due_on?->toDateString() ?? '9999-12-31')
            ->values();
        $payableByCurrency = [];
        $receivableByCurrency = [];
        $monthlyMinimumByCurrency = [];
        $nonMonetaryCount = 0;
        $unconvertedCurrencies = [];
        foreach ($activeRecords as $record) {
            if ($record->obligation_kind !== 'money') {
                $nonMonetaryCount++;

                continue;
            }

            foreach ($record->outstandingCurrencyBalances() as $currency => $balance) {
                $position = $record->currencyPosition($currency);
                if ($position['direction'] === 'payable') {
                    $payableByCurrency[$currency] = ($payableByCurrency[$currency] ?? 0) + $position['amount'];
                } elseif ($position['direction'] === 'receivable') {
                    $receivableByCurrency[$currency] = ($receivableByCurrency[$currency] ?? 0) + $position['amount'];
                }
                if ($currency !== $profile->base_currency) {
                    $unconvertedCurrencies[$currency] = true;
                }
            }

            if ($record->minimum_payment_amount !== null && $record->currentPositionDirection() === 'payable') {
                $currency = strtoupper((string) $record->currency);
                $monthlyMinimumByCurrency[$currency] = ($monthlyMinimumByCurrency[$currency] ?? 0) + (int) $record->minimum_payment_amount;
            }
        }
        $upcoming = (clone $active)
            ->whereNotNull('next_due_on')
            ->orderBy('next_due_on')
            ->with('record.partyLinks.party')
            ->limit(6)
            ->get();

        $profiles = $this->profiles()->get();
        $pawnRisks = app(PawnRiskService::class)->forProfile($profile);
        $budget = BudgetPeriod::query()
            ->where('profile_id', $profile->getKey())
            ->where('status', 'open')
            ->latest('starts_on')
            ->first();
        $plan = $budget?->repaymentPlans()
            ->whereIn('status', ['active', 'needs_review', 'completed'])
            ->latest('generated_at')
            ->first();
        $repaymentSummary = app(ProfileRepaymentSummary::class)->profileSummary($profile, $budget, $plan);

        return view('livewire.dashboard', compact('profile', 'profiles', 'payableByCurrency', 'receivableByCurrency', 'monthlyMinimumByCurrency', 'nonMonetaryCount', 'upcoming', 'unconvertedCurrencies', 'pawnRisks', 'reversedObligations', 'budget', 'plan', 'repaymentSummary'))
            ->layout('layouts.app', ['title' => 'Dashboard']);
    }

    /** @return Builder<FinancialProfile> */
    private function profiles(): Builder
    {
        return app(ProfileAccess::class)->accessibleProfiles(Auth::user());
    }
}
