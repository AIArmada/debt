<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\AuthorisePaymentSchedule;
use App\Actions\Obligations\CreatePaymentSchedule;
use App\Actions\Obligations\PausePaymentSchedule;
use App\Actions\Obligations\ResumePaymentSchedule;
use App\Models\Obligation;
use App\Models\PaymentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ManageSchedule extends Component
{
    public Obligation $obligation;

    #[Validate('required|in:manual_reminder,approval_required,automatic')]
    public string $mode = 'manual_reminder';

    #[Validate('required|numeric|gt:0')]
    public ?string $amount = null;

    #[Validate('required|in:weekly,monthly,quarterly')]
    public string $frequency = 'monthly';

    #[Validate('required|date')]
    public string $startsOn = '';

    #[Validate('required|date')]
    public string $nextRunsOn = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $perPaymentLimit = null;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('manageSchedule', $obligation);
        $this->obligation = $obligation;
        $this->startsOn = today()->toDateString();
        $this->nextRunsOn = today()->toDateString();
    }

    public function save(CreatePaymentSchedule $createPaymentSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);
        if (! $this->canSchedulePayments()) {
            $this->addError('amount', 'This obligation currently shows money to receive or is settled. Review the position before scheduling a payment.');

            return;
        }

        $validated = $this->validate();
        try {
            $createPaymentSchedule->handle($this->obligation, [
                'mode' => $validated['mode'],
                'amount' => $validated['amount'],
                'currency' => $this->obligation->currency,
                'frequency' => $validated['frequency'],
                'starts_on' => $validated['startsOn'],
                'next_runs_on' => $validated['nextRunsOn'],
                'per_payment_limit' => $validated['perPaymentLimit'],
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }
        $this->reset('amount', 'perPaymentLimit');
        session()->flash('schedule-created', 'The schedule was saved. It will not move money automatically.');
    }

    public function pause(string $scheduleId, PausePaymentSchedule $pausePaymentSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);
        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);
        $pausePaymentSchedule->handle($this->obligation, $schedule);
        session()->flash('schedule-paused', 'The schedule was paused.');
    }

    public function resume(string $scheduleId, ResumePaymentSchedule $resumePaymentSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);
        $schedule = $this->obligation->paymentSchedules()->whereKey($scheduleId)->firstOrFail();

        try {
            $resumePaymentSchedule->handle($this->obligation, $schedule);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('schedule-resumed', 'The schedule was resumed.');
    }

    public function canSchedulePayments(): bool
    {
        return $this->obligation->currentPositionDirection() === 'payable';
    }

    public function authorise(string $scheduleId, AuthorisePaymentSchedule $authorisePaymentSchedule): void
    {
        Gate::authorize('manageSchedule', $this->obligation);
        $schedule = $this->obligation->paymentSchedules()->whereKey($scheduleId)->firstOrFail();
        $authorisePaymentSchedule->handle(auth()->user(), $this->obligation, $schedule, 'sandbox', (int) $schedule->amount);
        session()->flash('schedule-authorised', 'Sandbox authorisation saved. Automatic execution is still limited to this local test provider.');
    }

    public function render(): View
    {
        Gate::authorize('manageSchedule', $this->obligation);

        return view('livewire.obligations.manage-schedule', [
            'schedules' => $this->obligation->paymentSchedules()->latest()->get(),
        ]);
    }
}
