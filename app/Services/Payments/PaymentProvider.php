<?php

namespace App\Services\Payments;

use App\Models\PaymentAuthorisation;
use App\Models\PaymentSchedule;

interface PaymentProvider
{
    /** @return array{status: string, external_reference: string, payload: array<string, mixed>} */
    public function charge(PaymentSchedule $schedule, PaymentAuthorisation $authorisation, string $idempotencyKey): array;
}
