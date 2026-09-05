<?php

namespace App\Services\Payments;

use App\Models\PaymentAuthorisation;
use App\Models\PaymentSchedule;
use RuntimeException;

class PaymentProviderManager
{
    /** @return array{external_reference: string, payload: array<string, mixed>} */
    public function charge(PaymentSchedule $schedule, PaymentAuthorisation $authorisation, string $idempotencyKey): array
    {
        return match ($authorisation->provider) {
            'sandbox' => app(SandboxPaymentProvider::class)->charge($schedule, $authorisation, $idempotencyKey),
            default => throw new RuntimeException('The selected payment provider is not configured on this server.'),
        };
    }
}
