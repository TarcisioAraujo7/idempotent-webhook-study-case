<?php

use App\Enums\PaymentWebhookReceiptStatus;
use App\Jobs\ProcessBankTransfer;
use App\Models\PaymentWebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

test('phase 4 requires an idempotency header before taking a redis lock', function (): void {
    Redis::shouldReceive('command')->never();

    $response = $this->postJson(paymentWebhookRoute(4), paymentWebhookPayload());

    $response
        ->assertStatus(400)
        ->assertJson([
            'message' => 'The Idempotency-Key header is required.',
        ]);

    Queue::assertNothingPushed();
    expect(PaymentWebhookReceipt::query()->count())->toBe(0);
});

test('phase 4 blocks a webhook when the redis lock already exists', function (): void {
    $providerKey = 'payment-demo-locked';
    $lockKey = providerWebhookIdempotencyKey($providerKey).':lock';

    Redis::shouldReceive('command')
        ->once()
        ->withArgs(function (string $command, array $parameters) use ($lockKey): bool {
            if ($command !== 'set' || count($parameters) !== 5) {
                return false;
            }

            [$key, $owner, $ttlMode, $ttl, $mode] = $parameters;

            return $key === $lockKey
                && is_string($owner)
                && $owner !== ''
                && $ttlMode === 'EX'
                && $ttl === 30
                && $mode === 'NX';
        })
        ->andReturn(false);

    $response = $this->postJson(paymentWebhookRoute(4), paymentWebhookPayload(), [
        'Idempotency-Key' => $providerKey,
    ]);

    $response
        ->assertStatus(409)
        ->assertJson([
            'message' => 'Request already processed',
        ]);

    Queue::assertNothingPushed();
    expect(PaymentWebhookReceipt::query()->count())->toBe(0);
});

test('phase 4 processes a new webhook and releases the redis lock', function (): void {
    $providerKey = 'payment-demo-0004';
    $lockKey = providerWebhookIdempotencyKey($providerKey).':lock';
    $lockOwner = null;

    Redis::shouldReceive('command')
        ->once()
        ->withArgs(function (string $command, array $parameters) use ($lockKey, &$lockOwner): bool {
            if ($command !== 'set' || count($parameters) !== 5) {
                return false;
            }

            [$key, $owner, $ttlMode, $ttl, $mode] = $parameters;
            $lockOwner = $owner;

            return $key === $lockKey
                && $owner !== ''
                && $ttlMode === 'EX'
                && $ttl === 30
                && $mode === 'NX';
        })
        ->andReturn(true);
    Redis::shouldReceive('command')
        ->once()
        ->withArgs(function (string $command, array $parameters) use ($lockKey, &$lockOwner): bool {
            if ($command !== 'eval' || count($parameters) !== 4) {
                return false;
            }

            [$script, $keys, $key, $owner] = $parameters;

            return $script !== ''
                && $keys === 1
                && $key === $lockKey
                && $owner === $lockOwner;
        })
        ->andReturn(1);

    $response = $this->postJson(paymentWebhookRoute(4), paymentWebhookPayload(), [
        'Idempotency-Key' => $providerKey,
    ]);

    $response
        ->assertCreated()
        ->assertJson([
            'message' => 'Phase 4 webhook processed successfully.',
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);

    $receipt = PaymentWebhookReceipt::query()->firstOrFail();

    expect($receipt->idempotency_key)->toBe(providerWebhookIdempotencyKey($providerKey))
        ->and($receipt->status)->toBe(PaymentWebhookReceiptStatus::PROCESSED)
        ->and($receipt->processed_at)->not->toBeNull();
});

test('phase 4 blocks a repeated idempotency key after acquiring the short redis lock', function (): void {
    $providerKey = 'payment-demo-0005';
    $payload = paymentWebhookPayload();
    $lockKey = providerWebhookIdempotencyKey($providerKey).':lock';
    $headers = ['Idempotency-Key' => $providerKey];

    Redis::shouldReceive('command')
        ->twice()
        ->withArgs(function (string $command, array $parameters) use ($lockKey): bool {
            if ($command !== 'set' || count($parameters) !== 5) {
                return false;
            }

            [$key, $owner, $ttlMode, $ttl, $mode] = $parameters;

            return $key === $lockKey
                && is_string($owner)
                && $owner !== ''
                && $ttlMode === 'EX'
                && $ttl === 30
                && $mode === 'NX';
        })
        ->andReturn(true, true);
    Redis::shouldReceive('command')
        ->twice()
        ->withArgs(function (string $command, array $parameters) use ($lockKey): bool {
            if ($command !== 'eval' || count($parameters) !== 4) {
                return false;
            }

            [$script, $keys, $key, $owner] = $parameters;

            return is_string($script)
                && $script !== ''
                && $keys === 1
                && $key === $lockKey
                && is_string($owner)
                && $owner !== '';
        })
        ->andReturn(1, 1);

    $firstResponse = $this->postJson(paymentWebhookRoute(4), $payload, $headers);
    $secondResponse = $this->postJson(paymentWebhookRoute(4), $payload, $headers);

    $firstResponse->assertCreated();
    $secondResponse
        ->assertStatus(409)
        ->assertJson([
            'message' => 'Request already processed',
        ]);

    Queue::assertPushed(ProcessBankTransfer::class, 1);
    expect(PaymentWebhookReceipt::query()->count())->toBe(1);
});
