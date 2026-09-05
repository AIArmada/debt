<?php

namespace App\Services;

use App\Models\Obligation;
use App\Models\PaymentSchedule;

class PaymentScheduleSafety
{
    public function pauseWhenNoLongerPayable(Obligation $obligation): int
    {
        if ($obligation->currentPositionDirection() === 'payable') {
            return 0;
        }

        return PaymentSchedule::query()
            ->where('obligation_id', $obligation->getKey())
            ->whereIn('status', ['active', 'awaiting_authorisation'])
            ->update(['status' => 'paused', 'paused_at' => now()]);
    }
}
