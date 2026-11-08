<?php

namespace App\Features\PromiseTracker\Domain\Presenters;

use App\Features\PromiseTracker\Domain\Enums\PaymentDestinationKind;
use App\Features\PromiseTracker\Models\PaymentDestination;

final class PaymentDestinationPresenter
{
    public function masked(PaymentDestination $destination): string
    {
        $details = preg_replace('/\s+/', '', (string) $destination->details_encrypted) ?? '';

        if ($destination->kind === PaymentDestinationKind::CashHandover) {
            return 'Cash handover details hidden';
        }

        if ($details === '') {
            return '••••';
        }

        return '•••• '.mb_substr($details, -4);
    }
}
