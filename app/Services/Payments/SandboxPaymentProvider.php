<?php

namespace App\Services\Payments;

use App\Models\PaymentAuthorisation;
use App\Models\PaymentSchedule;

class SandboxPaymentProvider implements PaymentProvider
{
    public function charge(PaymentSchedule $schedule, PaymentAuthorisation $authorisation, string $idempotencyKey): array
    {
        return ['status' => 'succeeded', 'external_reference' => 'sandbox-'.$idempotencyKey, 'payload' => ['sandbox' => true, 'authorisation_id' => $authorisation->getKey()]];
    }
}
