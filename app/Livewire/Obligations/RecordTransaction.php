<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\RecordTransaction as RecordTransactionAction;
use App\Domain\Money\Currency;
use App\Models\Obligation;
use App\Models\RepaymentPlanAllocation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class RecordTransaction extends Component
{
    public Obligation $obligation;

    public string $modalName = 'record-transaction';

    public ?string $repaymentPlanAllocationId = null;

    public ?RepaymentPlanAllocation $repaymentPlanAllocation = null;

    public ?string $collectionScheduleId = null;

    #[Validate('required|in:payment,collection,advance,interest,fee,adjustment,write_off,opening_balance')]
    public string $entryType = 'payment';

    #[Validate('nullable|in:increase,decrease')]
    public ?string $balanceEffect = null;

    #[Validate('required|regex:/^\d{1,16}(?:\.\d{1,4})?$/|gt:0')]
    public ?string $amount = null;

    #[Validate('required|string|size:3')]
    public string $currency = 'MYR';

    #[Validate('required|in:planned,submitted,confirmed,failed,cancelled')]
    public string $status = 'confirmed';

    #[Validate('required|date')]
    public string $occurredOn = '';

    #[Validate('nullable|string|max:255')]
    public string $externalReference = '';

    #[Validate('nullable|string|max:4000')]
    public string $note = '';

    public function mount(Obligation $obligation, string $modalName = 'record-transaction', ?string $repaymentPlanAllocationId = null): void
    {
        Gate::authorize('recordTransaction', $obligation);
        $this->obligation = $obligation;
        $this->modalName = $modalName;
        $this->repaymentPlanAllocationId = $repaymentPlanAllocationId;
        $this->repaymentPlanAllocation = $repaymentPlanAllocationId === null
            ? null
            : RepaymentPlanAllocation::query()
                ->with('plan')
                ->whereKey($repaymentPlanAllocationId)
                ->where('obligation_id', $obligation->getKey())
                ->firstOrFail();
        $this->entryType = $this->defaultDecreaseEntryType();
        $this->currency = (string) $obligation->currency;
        $this->occurredOn = today()->toDateString();
    }

    public function save(RecordTransactionAction $recordTransaction): void
    {
        Gate::authorize('recordTransaction', $this->obligation);
        $validated = $this->validate();
        $promotesSnapshotToLedger = $this->obligation->tracking_mode === 'snapshot';

        if ($validated['entryType'] === 'adjustment' && ! in_array($validated['balanceEffect'], ['increase', 'decrease'], true)) {
            $this->addError('balanceEffect', 'Choose whether this adjustment increases or decreases the balance.');

            return;
        }

        try {
            $recordTransaction->handle($this->obligation, [
                'status' => $validated['status'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'occurred_on' => $validated['occurredOn'],
                'external_reference' => $validated['externalReference'] ?: null,
                'note' => $validated['note'] ?: null,
                'entry_type' => $validated['entryType'],
                'balance_effect' => $validated['balanceEffect'],
                'repayment_plan_allocation_id' => $this->repaymentPlanAllocationId,
                'collection_schedule_id' => $this->collectionScheduleId,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->reset('amount', 'externalReference', 'note', 'collectionScheduleId');
        $this->status = 'confirmed';
        $this->currency = (string) $this->obligation->currency;
        $this->entryType = $this->defaultDecreaseEntryType();
        $this->balanceEffect = null;
        $this->occurredOn = today()->toDateString();
        $this->obligation->refresh();
        $this->dispatch('transaction-recorded');
        $this->dispatch('modal-close', name: $this->modalName);
        session()->flash(
            'transaction-recorded',
            $promotesSnapshotToLedger
                ? 'The movement was recorded. Detailed ledger tracking is now active for this record.'
                : 'The transaction was recorded.',
        );
    }

    public function render(): View
    {
        Gate::authorize('recordTransaction', $this->obligation);

        return view('livewire.obligations.record-transaction', [
            'currencies' => Currency::options(),
            'collectionSchedules' => $this->obligation->collectionSchedules()->where('status', 'active')->where('currency', strtoupper($this->currency))->get(),
        ]);
    }

    public function updatedCurrency(string $currency): void
    {
        if (in_array($this->entryType, ['payment', 'collection'], true)) {
            $this->entryType = $this->decreaseEntryTypeFor($currency);
        }
    }

    private function defaultDecreaseEntryType(): string
    {
        return $this->decreaseEntryTypeFor((string) $this->obligation->currency);
    }

    private function decreaseEntryTypeFor(string $currency): string
    {
        $direction = $this->obligation->currencyPosition($currency)['direction'] ?? $this->obligation->direction;

        return $direction === 'receivable' ? 'collection' : 'payment';
    }
}
