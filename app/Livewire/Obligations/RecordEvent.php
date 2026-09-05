<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\RecordObligationEvent;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class RecordEvent extends Component
{
    public Obligation $obligation;

    public string $modalName = 'record-fulfillment-event';

    #[Validate('required|string')]
    public string $eventType = 'note';

    #[Validate('nullable|numeric|gt:0')]
    public ?string $quantity = null;

    #[Validate('required|date')]
    public string $occurredOn = '';

    #[Validate('nullable|string|max:4000')]
    public string $note = '';

    public function mount(Obligation $obligation, string $modalName = 'record-fulfillment-event'): void
    {
        Gate::authorize('recordEvent', $obligation);
        $this->obligation = $obligation;
        $this->modalName = $modalName;
        $this->eventType = match ($obligation->kind()) {
            ObligationKind::Asset => 'returned',
            ObligationKind::Service, ObligationKind::Action => 'progress',
            ObligationKind::Money => 'note',
        };
        $this->occurredOn = today()->toDateString();
    }

    public function save(RecordObligationEvent $recordObligationEvent): void
    {
        Gate::authorize('recordEvent', $this->obligation);

        $validated = $this->validate([
            'eventType' => 'required|string',
            'quantity' => 'nullable|numeric|gt:0',
            'occurredOn' => 'required|date',
            'note' => 'nullable|string|max:4000',
        ]);

        if ($this->obligation->kind()->isQuantityBased() && filled($validated['quantity'])) {
            if (! Quantity::isModeCompatible($this->obligation->quantityMode(), $this->obligation->subject_unit)) {
                $this->addError('quantity', 'Choose a measurable unit such as gram, kilogram, hour, or metre before recording fractional progress.');

                return;
            }

            if (! Quantity::isValid($validated['quantity'], $this->obligation->quantityMode(), false)) {
                $this->addError('quantity', $this->obligation->quantityMode()->isCountable()
                    ? 'Whole-unit items must use a whole number, such as 1 camera or 2 cameras.'
                    : 'Enter a positive quantity with no more than four decimal places.');

                return;
            }
        }

        try {
            $recordObligationEvent->handle(auth()->user(), $this->obligation, [
                'event_type' => $validated['eventType'],
                'quantity' => $this->quantity,
                'occurred_on' => $validated['occurredOn'],
                'note' => $validated['note'] ?: null,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field === 'event_type' ? 'eventType' : $field, $messages[0]);
            }

            return;
        }

        $this->reset('quantity', 'note');
        $this->eventType = match ($this->obligation->kind()) {
            ObligationKind::Asset => 'returned',
            ObligationKind::Service, ObligationKind::Action => 'progress',
            ObligationKind::Money => 'note',
        };
        $this->occurredOn = today()->toDateString();
        $this->dispatch('event-recorded');
        $this->dispatch('modal-close', name: $this->modalName);
        session()->flash('event-recorded', 'The fulfillment update was recorded.');
    }

    public function render(): View
    {
        Gate::authorize('recordEvent', $this->obligation);

        return view('livewire.obligations.record-event');
    }
}
