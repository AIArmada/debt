<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\CreateCollectionSchedule;
use App\Actions\Obligations\PauseCollectionSchedule;
use App\Actions\Obligations\ResumeCollectionSchedule;
use App\Domain\Money\Currency;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ManageCollectionSchedule extends Component
{
    public Obligation $obligation;

    public string $mode = 'manual_follow_up';

    public ?string $amount = null;

    public string $currency = 'MYR';

    public string $frequency = 'daily';

    public string $startsOn = '';

    public string $nextDueOn = '';

    public ?string $endsOn = null;

    public string $collectionMethod = 'bank_transfer';

    public ?string $collectionAccountId = null;

    public int $graceDays = 0;

    public string $note = '';

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('manageSchedule', $obligation);
        $this->obligation = $obligation;
        $this->currency = (string) $obligation->currency;
        $this->startsOn = today()->toDateString();
        $this->nextDueOn = today()->toDateString();
    }

    public function save(CreateCollectionSchedule $createCollectionSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);

        if (! $this->canScheduleCollections()) {
            $this->addError('currency', 'This currency exposure does not currently show money to receive. Choose another currency exposure or review the current position.');

            return;
        }

        $validated = $this->validate([
            'mode' => 'required|in:manual_follow_up,bank_reconciliation',
            'amount' => 'required|numeric|gt:0',
            'currency' => ['required', Rule::in(Currency::codes())],
            'frequency' => 'required|in:daily,weekly,monthly,quarterly',
            'startsOn' => 'required|date',
            'nextDueOn' => 'required|date',
            'endsOn' => 'nullable|date|after_or_equal:startsOn',
            'collectionMethod' => 'required|in:bank_transfer,cash,payment_provider,other',
            'collectionAccountId' => 'nullable|uuid',
            'graceDays' => 'required|integer|min:0|max:60',
            'note' => 'nullable|string|max:4000',
        ]);

        try {
            $createCollectionSchedule->handle(auth()->user(), $this->obligation, [
                'mode' => $validated['mode'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'frequency' => $validated['frequency'],
                'starts_on' => $validated['startsOn'],
                'next_due_on' => $validated['nextDueOn'],
                'ends_on' => $validated['endsOn'],
                'collection_method' => $validated['collectionMethod'],
                'collection_account_id' => $validated['collectionAccountId'],
                'grace_days' => $validated['graceDays'],
                'note' => $validated['note'] ?: null,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->reset('amount', 'endsOn', 'note');
        session()->flash('collection-schedule-created', 'The collection schedule was saved. Incoming money is still recorded only when you confirm a collection or match a bank row.');
    }

    public function pause(string $scheduleId, PauseCollectionSchedule $pauseCollectionSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);
        $schedule = $this->obligation->collectionSchedules()->whereKey($scheduleId)->firstOrFail();
        $pauseCollectionSchedule->handle($this->obligation, $schedule);
        session()->flash('collection-schedule-paused', 'The collection schedule was paused.');
    }

    public function resume(string $scheduleId, ResumeCollectionSchedule $resumeCollectionSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);
        $schedule = $this->obligation->collectionSchedules()->whereKey($scheduleId)->firstOrFail();

        try {
            $resumeCollectionSchedule->handle($this->obligation, $schedule);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('collection-schedule-resumed', 'The collection schedule was resumed.');
    }

    public function canScheduleCollections(): bool
    {
        return $this->obligation->obligation_kind === 'money'
            && $this->obligation->currencyPosition($this->currency)['direction'] === 'receivable';
    }

    public function render(): View
    {
        Gate::authorize('manageSchedule', $this->obligation);

        return view('livewire.obligations.manage-collection-schedule', [
            'schedules' => $this->obligation->collectionSchedules()->latest()->get(),
            'collectionAccounts' => $this->obligation->record->profile->collectionAccounts()->where('status', 'active')->where('currency', strtoupper($this->currency))->get(),
            'currencies' => $this->collectibleCurrencies(),
        ]);
    }

    /** @return list<string> */
    private function collectibleCurrencies(): array
    {
        $currencies = collect($this->obligation->currencyPositions())
            ->filter(fn (array $position): bool => $position['direction'] === 'receivable')
            ->pluck('currency')
            ->values()
            ->all();

        return $currencies !== [] ? $currencies : [strtoupper((string) $this->obligation->currency)];
    }
}
