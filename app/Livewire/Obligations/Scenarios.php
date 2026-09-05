<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\CreateCalculationScenario;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Scenarios extends Component
{
    public Obligation $obligation;

    #[Validate('required|string|max:120')]
    public string $name = 'Extra payment plan';

    #[Validate('required|numeric|min:0')]
    public string $extraPayment = '0';

    #[Validate('required|in:weekly,monthly,quarterly')]
    public string $paymentFrequency = 'monthly';

    #[Validate('required|integer|min:1|max:60')]
    public int $horizonMonths = 12;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('view', $obligation);
        $this->obligation = $obligation;
    }

    public function save(CreateCalculationScenario $createCalculationScenario): void
    {
        Gate::authorize('view', $this->obligation);

        if (! $this->canProjectPayments()) {
            $this->addError('extraPayment', 'Payment scenarios are only available while the current position is something you need to pay. Review the reversed or receivable position first.');

            return;
        }

        $validated = $this->validate();
        $createCalculationScenario->handle(auth()->user(), $this->obligation, [
            'name' => $validated['name'],
            'extra_payment' => $validated['extraPayment'],
            'payment_frequency' => $validated['paymentFrequency'],
            'horizon_months' => $validated['horizonMonths'],
        ]);
        $this->reset('extraPayment');
        $this->name = 'Extra payment plan';
        $this->horizonMonths = 12;
        session()->flash('scenario-created', 'The estimate was saved with its assumptions.');
    }

    public function canProjectPayments(): bool
    {
        return $this->obligation->currentPositionDirection() === 'payable';
    }

    public function render(): View
    {
        Gate::authorize('view', $this->obligation);

        return view('livewire.obligations.scenarios', [
            'scenarios' => $this->obligation->calculationScenarios()
                ->select(['id', 'obligation_id', 'name', 'extra_payment', 'horizon_months', 'result', 'created_at'])
                ->with(['obligation' => fn ($query) => $query->select(['id', 'currency'])])
                ->limit(5)
                ->get(),
        ]);
    }
}
