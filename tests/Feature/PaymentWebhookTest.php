<?php

use App\Models\Obligation;
use App\Models\PaymentExecutionAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('payment webhooks fail closed when verification is not configured', function () {
    config()->set('services.payments.webhook_secret', null);

    $this->postJson(route('webhooks.payments', ['provider' => 'sandbox']), [
        'id' => 'event-1',
        'status' => 'succeeded',
    ])->assertServiceUnavailable();
});

test('duplicate payment webhooks are idempotent', function () {
    config()->set('services.payments.webhook_secret', 'test-secret');
    $payload = ['id' => 'event-duplicate', 'type' => 'payment.updated', 'status' => 'succeeded'];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, 'test-secret');

    $this->withHeaders(['X-Payment-Signature' => $signature])
        ->postJson(route('webhooks.payments', ['provider' => 'sandbox']), $payload)
        ->assertOk();

    $this->withHeaders(['X-Payment-Signature' => $signature])
        ->postJson(route('webhooks.payments', ['provider' => 'sandbox']), $payload)
        ->assertOk();

    $this->assertDatabaseCount('provider_webhook_events', 1);
    $this->assertDatabaseHas('provider_webhook_events', [
        'provider' => 'sandbox',
        'external_event_id' => 'event-duplicate',
        'status' => 'processed',
    ]);
});

test('payment webhooks update the serialized execution attempt', function () {
    $obligation = Obligation::factory()->create();
    $schedule = $obligation->paymentSchedules()->create([
        'mode' => 'automatic',
        'status' => 'active',
        'amount' => 1000,
        'currency' => 'MYR',
        'frequency' => 'monthly',
        'starts_on' => today(),
        'next_runs_on' => today(),
    ]);
    PaymentExecutionAttempt::create([
        'payment_schedule_id' => $schedule->getKey(),
        'idempotency_key' => 'attempt-1',
        'provider' => 'sandbox',
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => 'pending',
        'attempted_at' => now(),
    ]);

    config()->set('services.payments.webhook_secret', 'test-secret');
    $payload = [
        'id' => 'event-attempt',
        'idempotency_key' => 'attempt-1',
        'status' => 'succeeded',
        'external_reference' => 'provider-1',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->withHeaders(['X-Payment-Signature' => hash_hmac('sha256', $body, 'test-secret')])
        ->postJson(route('webhooks.payments', ['provider' => 'sandbox']), $payload)
        ->assertOk();

    $this->assertDatabaseHas('payment_execution_attempts', [
        'idempotency_key' => 'attempt-1',
        'status' => 'succeeded',
        'external_reference' => 'provider-1',
    ]);
});
