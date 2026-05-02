<?php

use App\Jobs\ProcessBankTransfer;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

test('phase 1 processes a valid payment webhook', function (): void {
    $response = $this->postJson(paymentWebhookRoute(1), paymentWebhookPayload());

    $response
        ->assertCreated()
        ->assertJson([
            'message' => 'Phase 1 webhook processed successfully.',
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);
});

test('phase 1 allows repeated payment webhooks', function (): void {
    $payload = paymentWebhookPayload();

    $firstResponse = $this->postJson(paymentWebhookRoute(1), $payload);
    $secondResponse = $this->postJson(paymentWebhookRoute(1), $payload);

    $firstResponse->assertCreated();
    $secondResponse->assertCreated();

    Queue::assertPushed(ProcessBankTransfer::class, 2);
});

test('phase 1 rejects invalid payment webhook payloads', function (): void {
    $response = $this->postJson(paymentWebhookRoute(1), paymentWebhookPayload([
        'payer_document' => '',
    ]));

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payer_document']);

    Queue::assertNothingPushed();
});
