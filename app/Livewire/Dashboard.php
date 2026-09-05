<?php

namespace App\Livewire;

use App\Domain\Pawn\PawnRiskService;
use App\Domain\Planning\ProfileRepaymentSummary;
use App\Livewire\Concerns\InteractsWithAccessibleProfiles;
use App\Models\BudgetPeriod;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    use InteractsWithAccessibleProfiles;

    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public function mount(): void
    {
        $profiles = $this->accessibleProfilesCollection();
        $requestedProfileId = request()->query('profile');
        if (is_string($requestedProfileId) && $profiles->contains('id', $requestedProfileId)) {
            $this->profileId = $requestedProfileId;
            session()->put('selected_profile_id', $requestedProfileId);

            return;
        }

        $selectedProfileId = session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $profiles->contains('id', $selectedProfileId)
            ? $selectedProfileId
            : $profiles->first()?->getKey();

        abort_if($this->profileId === null, 403);
    }

    public function updatedProfileId(): void
    {
        $this->accessibleProfile($this->profileId);
        session()->put('selected_profile_id', $this->profileId);
    }

    public function render(): View
    {
        $profiles = $this->accessibleProfilesCollection();
        $profile = $this->accessibleProfile($this->profileId);
        $active = Obligation::query()
            ->whereHas('record', fn ($query) => $query->where('profile_id', $profile->getKey())->where('is_archived', false))
            ->active();

        $activeRecords = (clone $active)
            ->select(['id', 'record_id', 'direction', 'obligation_kind', 'currency', 'current_total_balance', 'currency_balances', 'minimum_payment_amount', 'next_due_on'])
            ->lazyById(500);
        $reversedObligations = collect();
        $payableByCurrency = [];
        $receivableByCurrency = [];
        $monthlyMinimumByCurrency = [];
        $summaryRows = [];
        $summaryService = app(ProfileRepaymentSummary::class);
        $nonMonetaryCount = 0;
        $unconvertedCurrencies = [];
        foreach ($activeRecords as $record) {
            $summaryService->addObligationToSummaryRows($summaryRows, $record);
            if ($record->isPositionReversed()) {
                $reversedObligations->push($record);
            }

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
        $reversedIds = $reversedObligations->pluck('id')->all();
        $reversedObligations = $reversedIds === []
            ? collect()
            : Obligation::query()
                ->whereKey($reversedIds)
                ->select(['id', 'record_id', 'direction', 'obligation_kind', 'currency', 'current_total_balance', 'currency_balances', 'next_due_on', 'title'])
                ->with('record:id,title')
                ->get()
                ->sortBy(fn (Obligation $obligation): string => $obligation->next_due_on?->toDateString() ?? '9999-12-31')
                ->values();
        $upcoming = (clone $active)
            ->select(['id', 'record_id', 'direction', 'obligation_kind', 'currency', 'current_total_balance', 'currency_balances', 'subject_unit', 'current_subject_quantity', 'next_due_on', 'title'])
            ->whereNotNull('next_due_on')
            ->orderBy('next_due_on')
            ->with([
                'record:id,title',
                'record.partyLinks:id,record_id,party_id,role,is_primary',
                'record.partyLinks.party:id,preferred_name',
            ])
            ->limit(6)
            ->get();

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
        $repaymentSummary = $summaryService->profileSummaryFromRows($summaryRows, $budget, $plan);

        return view('livewire.dashboard', compact('profile', 'profiles', 'payableByCurrency', 'receivableByCurrency', 'monthlyMinimumByCurrency', 'nonMonetaryCount', 'upcoming', 'unconvertedCurrencies', 'pawnRisks', 'reversedObligations', 'budget', 'plan', 'repaymentSummary'))
            ->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
