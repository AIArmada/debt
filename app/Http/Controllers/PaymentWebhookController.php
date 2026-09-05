<?php

namespace App\Http\Controllers;

use App\Models\PaymentExecutionAttempt;
use App\Models\ProviderWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('services.payments.webhook_secret', '');
        $signature = (string) $request->header('X-Payment-Signature', '');
        if ($secret === '') {
            abort(503, 'Payment webhook verification is not configured.');
        }
        if (! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            abort(401, 'Invalid provider signature.');
        }

        $payload = $request->json()->all();
        $externalId = (string) ($payload['id'] ?? $payload['event_id'] ?? '');
        if ($externalId === '') {
            return response()->json(['message' => 'An event id is required.'], 422);
        }

        $event = DB::transaction(function () use ($provider, $externalId, $payload): ProviderWebhookEvent {
            $timestamp = now();
            ProviderWebhookEvent::query()->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'provider' => $provider,
                'external_event_id' => $externalId,
                'event_type' => (string) ($payload['type'] ?? 'payment.updated'),
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'received',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $event = ProviderWebhookEvent::query()
                ->where('provider', $provider)
                ->where('external_event_id', $externalId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($event->status === 'processed') {
                return $event;
            }
            $key = $payload['idempotency_key'] ?? null;
            if (is_string($key)) {
                $attempt = PaymentExecutionAttempt::query()->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($attempt !== null) {
                    $attempt->update(['status' => ($payload['status'] ?? 'succeeded') === 'succeeded' ? 'succeeded' : 'failed', 'external_reference' => $payload['external_reference'] ?? $attempt->external_reference, 'response_payload' => $payload, 'completed_at' => now()]);
                }
            }
            $event->update(['status' => 'processed', 'processed_at' => now(), 'payload' => $payload]);

            return $event;
        });

        return response()->json(['received' => true, 'event_id' => $event->external_event_id]);
    }
}
