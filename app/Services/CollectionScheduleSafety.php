<?php

namespace App\Services;

use App\Models\CollectionSchedule;
use App\Models\Obligation;

class CollectionScheduleSafety
{
    public function pauseWhenNoLongerReceivable(Obligation $obligation): int
    {
        $schedules = CollectionSchedule::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('status', 'active')
            ->get();
        $paused = 0;

        foreach ($schedules as $schedule) {
            if ($obligation->currencyPosition((string) $schedule->currency)['direction'] !== 'receivable') {
                $schedule->update(['status' => 'paused', 'paused_at' => now()]);
                $paused++;
            }
        }

        return $paused;
    }
}
