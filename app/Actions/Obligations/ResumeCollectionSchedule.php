<?php

namespace App\Actions\Obligations;

use App\Models\CollectionSchedule;
use App\Models\Obligation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResumeCollectionSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Obligation $obligation, CollectionSchedule $schedule): CollectionSchedule
    {
        Gate::authorize('manageSchedule', $obligation);
        abort_unless($schedule->obligation_id === $obligation->getKey(), 404);

        if ($obligation->obligation_kind !== 'money' || $obligation->currencyPosition((string) $schedule->currency)['direction'] !== 'receivable') {
            throw ValidationException::withMessages(['schedule' => 'A collection schedule can resume only while its currency exposure is money you expect to receive.']);
        }

        $schedule->update(['status' => 'active', 'paused_at' => null]);
        $this->auditLogger->record($obligation->record->profile, null, CollectionSchedule::class, $schedule->getKey(), 'resumed', after: $schedule->only(['obligation_id', 'status', 'paused_at']));

        return $schedule->refresh();
    }
}
