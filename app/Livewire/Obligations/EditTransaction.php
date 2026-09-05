<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\UpdateTransaction as UpdateTransactionAction;
use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class EditTransaction extends Component
{
    public Obligation $obligation;

    public FinancialTransaction $transaction;

    public string $entryType = 'payment';

    public ?string $balanceEffect = null;

    public ?string $amount = null;

    public string $currency = 'MYR';

    public string $status = 'confirmed';

    public string $occurredOn = '';

    public string $externalReference = '';

    public string $note = '';

    public function mount(Obligation $obligation, FinancialTransaction $transaction): void
    {
        Gate::authorize('recordTransaction', $obligation);

        if ($transaction->obligation_id !== $obligation->getKey()) {
            abort(404);
        }

        $this->obligation = $obligation;
        $this->transaction = $transaction;
        $this->entryType = $transaction->entry_type;
        $this->balanceEffect = $transaction->balance_effect;
        $this->currency = (string) $transaction->currency;
        $this->amount = MoneyAmount::majorInput((int) $transaction->amount, $this->currency);
        $this->status = $transaction->status;
        $this->occurredOn = $transaction->occurred_on?->format('Y-m-d') ?? today()->toDateString();
        $this->externalReference = (string) ($transaction->external_reference ?? '');
        $this->note = (string) ($transaction->note ?? '');
    }

    public function save(UpdateTransactionAction $updateTransaction): void
    {
        Gate::authorize('recordTransaction', $this->obligation);

        $validated = $this->validate([
            'entryType' => 'required|in:payment,collection,advance,interest,fee,adjustment,write_off,opening_balance',
            'balanceEffect' => 'nullable|in:increase,decrease',
            'amount' => 'required|regex:/^\d{1,16}(?:\.\d{1,4})?$/|gt:0',
            'currency' => 'required|string|size:3',
            'status' => 'required|in:planned,submitted,confirmed,failed,cancelled',
            'occurredOn' => 'required|date',
            'externalReference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:4000',
        ]);

        if ($validated['entryType'] === 'adjustment' && ! in_array($validated['balanceEffect'], ['increase', 'decrease'], true)) {
            $this->addError('balanceEffect', 'Choose whether this adjustment increases or decreases the balance.');

            return;
        }

        try {
            $updateTransaction->handle($this->obligation, $this->transaction, [
                'status' => $validated['status'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'occurred_on' => $validated['occurredOn'],
                'external_reference' => $validated['externalReference'] ?: null,
                'note' => $validated['note'] ?: null,
                'entry_type' => $validated['entryType'],
                'balance_effect' => $validated['balanceEffect'],
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->dispatch('transaction-updated');
        $this->dispatch('modal-close', name: 'edit-transaction-'.$this->transaction->getKey());
        session()->flash('transaction-updated', 'The movement was updated and later balances were recalculated.');
    }

    public function render(): View
    {
        Gate::authorize('recordTransaction', $this->obligation);

        return view('livewire.obligations.edit-transaction', ['currencies' => Currency::options()]);
    }
}
