<?php

use App\Enums\PaymentWebhookReceiptStatus;
use App\Jobs\ProcessBankTransfer;
use App\Models\PaymentWebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

test('phase 3 requires an idempotency header', function (): void {
    $response = $this->postJson(paymentWebhookRoute(3), paymentWebhookPayload());

    $response
        ->assertStatus(400)
        ->assertJson([
            'message' => 'The Idempotency-Key header is required.',
        ]);

    Queue::assertNothingPushed();
    expect(PaymentWebhookReceipt::query()->count())->toBe(0);
});

test('phase 3 stores the receipt and processes a new idempotency key', function (): void {
    $providerKey = 'payment-demo-0001';
    $payload = paymentWebhookPayload([
        'payer_name' => '  Joao   Silva  ',
        'payer_document' => '123.456.789-00',
        'bank_code' => ' br1 ',
    ]);

    $response = $this->postJson(paymentWebhookRoute(3), $payload, [
        'Idempotency-Key' => $providerKey,
    ]);

    $response
        ->assertCreated()
        ->assertJson([
            'message' => 'Phase 3 webhook processed successfully.',
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);

    $receipt = PaymentWebhookReceipt::query()->firstOrFail();

    expect($receipt->idempotency_key)->toBe(providerWebhookIdempotencyKey($providerKey))
        ->and($receipt->status)->toBe(PaymentWebhookReceiptStatus::PROCESSED)
        ->and($receipt->payload)->toMatchArray([
            'payer_name' => 'Joao Silva',
            'payer_document' => '12345678900',
            'amount_in_cents' => 15000,
            'bank_code' => 'BR1',
            'branch_number' => '1234',
            'account_number' => '56789-0',
        ])
        ->and($receipt->processed_at)->not->toBeNull()
        ->and($receipt->failed_at)->toBeNull();
});

test('phase 3 blocks a repeated idempotency key from mysql history', function (): void {
    $providerKey = 'payment-demo-0002';
    $payload = paymentWebhookPayload();
    $headers = ['Idempotency-Key' => $providerKey];

    $firstResponse = $this->postJson(paymentWebhookRoute(3), $payload, $headers);
    $secondResponse = $this->postJson(paymentWebhookRoute(3), $payload, $headers);

    $firstResponse->assertCreated();
    $secondResponse
        ->assertStatus(409)
        ->assertJson([
            'message' => 'Request already processed.',
            'status' => PaymentWebhookReceiptStatus::PROCESSED->value,
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);
    expect(PaymentWebhookReceipt::query()->count())->toBe(1);
});

test('phase 3 accepts the x idempotency key header alias', function (): void {
    $providerKey = 'payment-demo-x-header';

    $response = $this->postJson(paymentWebhookRoute(3), paymentWebhookPayload(), [
        'X-Idempotency-Key' => $providerKey,
    ]);

    $response->assertCreated();

    Queue::assertPushed(ProcessBankTransfer::class, 1);
    expect(PaymentWebhookReceipt::query()->firstOrFail()->idempotency_key)
        ->toBe(providerWebhookIdempotencyKey($providerKey));
});
