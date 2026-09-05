<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\SaveObligationTerm;
use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EditTerms extends Component
{
    public Obligation $obligation;

    #[Validate('required|in:none,simple_interest,compound_interest,storage_fee,fixed_installment')]
    public string $calculationMethod = 'none';

    #[Validate('nullable|numeric|min:0')]
    public ?string $interestRate = null;

    #[Validate('nullable|in:annual,monthly,weekly')]
    public ?string $interestPeriod = 'annual';

    #[Validate('nullable|in:monthly,weekly,daily')]
    public ?string $compoundingPeriod = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $lateFeeAmount = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $lateFeeRate = null;

    #[Validate('required|integer|min:0|max:365')]
    public int $gracePeriodDays = 0;

    #[Validate('boolean')]
    public bool $lateFeeRecurring = false;

    #[Validate('nullable|numeric|min:0')]
    public ?string $storageFeeAmount = null;

    #[Validate('nullable|in:monthly,weekly,daily')]
    public ?string $storageFeePeriod = 'monthly';

    #[Validate('nullable|numeric|min:0')]
    public ?string $fixedInstallmentAmount = null;

    #[Validate('required|date')]
    public string $effectiveFrom = '';

    #[Validate('nullable|string|max:4000')]
    public string $sourceNote = '';

    #[Validate('boolean')]
    public bool $sourceVerified = false;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('manageTerms', $obligation);
        $this->obligation = $obligation;
        $this->effectiveFrom = today()->toDateString();
        $this->fixedInstallmentAmount = $obligation->minimum_payment_amount === null
            ? null
            : MoneyAmount::majorInput((int) $obligation->minimum_payment_amount, (string) $obligation->currency);
    }

    public function save(SaveObligationTerm $saveObligationTerm): void
    {
        Gate::authorize('manageTerms', $this->obligation);
        $validated = $this->validate();

        $saveObligationTerm->handle($this->obligation, [
            'calculation_method' => $validated['calculationMethod'],
            'interest_rate' => $validated['interestRate'],
            'interest_period' => $validated['interestPeriod'],
            'compounding_period' => $validated['compoundingPeriod'],
            'late_fee_amount' => $validated['lateFeeAmount'],
            'late_fee_rate' => $validated['lateFeeRate'],
            'grace_period_days' => $validated['gracePeriodDays'],
            'late_fee_recurring' => $validated['lateFeeRecurring'],
            'storage_fee_amount' => $validated['storageFeeAmount'],
            'storage_fee_period' => $validated['storageFeePeriod'],
            'fixed_installment_amount' => $validated['fixedInstallmentAmount'],
            'effective_from' => $validated['effectiveFrom'],
            'source_note' => $validated['sourceNote'],
            'source_verified' => $validated['sourceVerified'],
        ]);

        $this->reset('interestRate', 'compoundingPeriod', 'lateFeeAmount', 'lateFeeRate', 'storageFeeAmount', 'fixedInstallmentAmount', 'sourceNote');
        $this->calculationMethod = 'none';
        $this->interestPeriod = 'annual';
        $this->storageFeePeriod = 'monthly';
        $this->sourceVerified = false;
        $this->gracePeriodDays = 0;
        $this->lateFeeRecurring = false;
        $this->effectiveFrom = today()->toDateString();
        $this->dispatch('terms-updated');
        session()->flash('terms-updated', 'A new terms version was saved.');
    }

    public function render(): View
    {
        Gate::authorize('manageTerms', $this->obligation);

        return view('livewire.obligations.edit-terms');
    }
}
