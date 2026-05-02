<?php

use App\Jobs\ProcessBankTransfer;
use App\Services\PaymentIdempotencyKeyGenerator;
use App\Services\PaymentPayloadNormalizer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    Queue::fake();
});

test('phase 2 processes a webhook when the redis idempotency key is new', function (): void {
    $payload = paymentWebhookPayload([
        'payer_name' => '  Joao   Silva  ',
        'bank_code' => ' br1 ',
    ]);
    $idempotencyKey = generatedPayloadIdempotencyKey($payload);

    Redis::shouldReceive('command')
        ->once()
        ->with('set', [$idempotencyKey, 1, 'EX', 30, 'NX'])
        ->andReturn(true);
    Redis::shouldReceive('del')->never();

    $response = $this->postJson(paymentWebhookRoute(2), $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'message' => 'Phase 2 webhook processed successfully.',
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);
});

test('phase 2 blocks a repeated logical payload after normalization', function (): void {
    $firstPayload = paymentWebhookPayload([
        'payer_name' => '  Joao   Silva  ',
        'payer_document' => '123.456.789-00',
        'bank_code' => ' br1 ',
    ]);
    $secondPayload = [
        'account_number' => '56789-0',
        'branch_number' => '1234',
        'bank_code' => 'BR1',
        'amount_in_cents' => '15000',
        'payer_document' => '12345678900',
        'payer_name' => 'Joao Silva',
    ];
    $idempotencyKey = generatedPayloadIdempotencyKey($firstPayload);

    Redis::shouldReceive('command')
        ->twice()
        ->with('set', [$idempotencyKey, 1, 'EX', 30, 'NX'])
        ->andReturn(true, false);
    Redis::shouldReceive('del')->never();

    $firstResponse = $this->postJson(paymentWebhookRoute(2), $firstPayload);
    $secondResponse = $this->postJson(paymentWebhookRoute(2), $secondPayload);

    $firstResponse->assertCreated();
    $secondResponse
        ->assertStatus(409)
        ->assertJson([
            'message' => 'Request already processed',
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);
});

test('phase 2 releases the redis key when payload validation fails', function (): void {
    $payload = paymentWebhookPayload([
        'amount_in_cents' => -1,
    ]);
    $idempotencyKey = generatedPayloadIdempotencyKey($payload);

    Redis::shouldReceive('command')
        ->once()
        ->with('set', [$idempotencyKey, 1, 'EX', 30, 'NX'])
        ->andReturn(true);
    Redis::shouldReceive('del')
        ->once()
        ->with($idempotencyKey)
        ->andReturn(1);

    $response = $this->postJson(paymentWebhookRoute(2), $payload);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_in_cents']);

    Queue::assertNothingPushed();
});

/**
 * @param  array<string, mixed>  $payload
 */
function generatedPayloadIdempotencyKey(array $payload): string
{
    $normalizedPayload = app(PaymentPayloadNormalizer::class)->normalize($payload);

    return app(PaymentIdempotencyKeyGenerator::class)->generate($normalizedPayload);
}
