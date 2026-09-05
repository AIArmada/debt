<?php

namespace App\Actions\Obligations;

use App\Models\Obligation;
use App\Models\PaymentSchedule;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;

class PausePaymentSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Obligation $obligation, PaymentSchedule $schedule): PaymentSchedule
    {
        Gate::authorize('manageSchedule', $obligation);
        abort_unless($schedule->obligation_id === $obligation->getKey(), 404);
        $schedule->update(['status' => 'paused', 'paused_at' => now()]);

        $this->auditLogger->record(
            $obligation->record->profile,
            null,
            PaymentSchedule::class,
            $schedule->getKey(),
            'paused',
            after: $schedule->only(['obligation_id', 'status', 'paused_at']),
        );

        return $schedule->refresh();
    }
}
