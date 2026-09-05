<?php

namespace App\Actions\Obligations;

use App\Models\CollectionSchedule;
use App\Models\Obligation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;

class PauseCollectionSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Obligation $obligation, CollectionSchedule $schedule): CollectionSchedule
    {
        Gate::authorize('manageSchedule', $obligation);
        abort_unless($schedule->obligation_id === $obligation->getKey(), 404);

        $schedule->update(['status' => 'paused', 'paused_at' => now()]);
        $this->auditLogger->record($obligation->record->profile, null, CollectionSchedule::class, $schedule->getKey(), 'paused', after: $schedule->only(['obligation_id', 'status', 'paused_at']));

        return $schedule->refresh();
    }
}
