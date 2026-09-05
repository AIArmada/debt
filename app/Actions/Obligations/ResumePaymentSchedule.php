<?php

namespace App\Actions\Obligations;

use App\Models\Obligation;
use App\Models\PaymentSchedule;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResumePaymentSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Obligation $obligation, PaymentSchedule $schedule): PaymentSchedule
    {
        Gate::authorize('manageSchedule', $obligation);
        abort_unless($schedule->obligation_id === $obligation->getKey(), 404);

        if ($obligation->currentPositionDirection() !== 'payable') {
            throw ValidationException::withMessages(['schedule' => 'A schedule can resume only while the current position is something you need to pay.']);
        }

        $schedule->update(['status' => 'active', 'paused_at' => null]);
        $this->auditLogger->record(
            $obligation->record->profile,
            null,
            PaymentSchedule::class,
            $schedule->getKey(),
            'resumed',
            after: $schedule->only(['obligation_id', 'status', 'paused_at']),
        );

        return $schedule->refresh();
    }
}
