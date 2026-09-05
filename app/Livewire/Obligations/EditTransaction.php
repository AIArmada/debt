<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\UpdateTransaction as UpdateTransactionAction;
use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

    public ?string $paymentInstructionId = null;

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
        $occurredOn = $transaction->occurred_on;
        $this->occurredOn = $occurredOn instanceof DateTimeInterface
            ? $occurredOn->format('Y-m-d')
            : today()->toDateString();
        $this->externalReference = (string) ($transaction->external_reference ?? '');
        $this->note = (string) ($transaction->note ?? '');
        $this->paymentInstructionId = $transaction->payment_instruction_id;
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
            'paymentInstructionId' => 'nullable|uuid',
        ]);

        if ($validated['entryType'] === 'adjustment' && ! in_array($validated['balanceEffect'], ['increase', 'decrease'], true)) {
            $this->addError('balanceEffect', 'Choose whether this adjustment increases or decreases the balance.');

            return;
        }

        if (! $this->hasValidPrecision($validated['amount'], $validated['currency'])) {
            $this->addError('amount', $this->precisionMessage($validated['amount'], $validated['currency']));

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
                'payment_instruction_id' => filled($this->paymentInstructionId) ? $this->paymentInstructionId : null,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(Str::camel($field), $message);
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

        return view('livewire.obligations.edit-transaction', [
            'currencies' => Currency::options(),
            'paymentInstructions' => $this->obligation->paymentInstructions()->where('status', 'active')->with('paymentDestination:id,label,status')->latest()->get(),
        ]);
    }

    private function hasValidPrecision(?string $amount, string $currency): bool
    {
        if ($amount === null) {
            return true;
        }

        try {
            MoneyAmount::fromMajor($amount, $currency);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    private function precisionMessage(?string $amount, string $currency): string
    {
        try {
            MoneyAmount::fromMajor($amount, $currency);
        } catch (\InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'Enter a valid amount.';
    }
}
