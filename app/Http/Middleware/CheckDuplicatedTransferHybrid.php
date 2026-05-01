<?php

namespace App\Http\Middleware;

use App\Enums\PaymentWebhookReceiptStatus;
use App\Models\PaymentWebhookReceipt;
use App\Services\PaymentPayloadNormalizer;
use App\Services\PaymentWebhookIdempotencyKeyResolver;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckDuplicatedTransferHybrid
{
    public function __construct(
        private readonly PaymentPayloadNormalizer $payloadNormalizer,
        private readonly PaymentWebhookIdempotencyKeyResolver $idempotencyKeyResolver,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $normalizedPayload = $this->payloadNormalizer->normalize($request->toArray());
        $request->merge($normalizedPayload);

        $idempotencyKey = $this->idempotencyKeyResolver->resolve($request);

        if ($idempotencyKey === null) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
            ], 400);
        }

        $lockKey = $idempotencyKey . ':lock';
        $lockOwner = (string) Str::uuid();

        if (! Redis::set($lockKey, $lockOwner, 'EX', 30, 'NX')) {
            return response()->json(['message' => 'Request already processed'], 409);
        }

        $receipt = null;

        try {
            $receipt = PaymentWebhookReceipt::query()->create([
                'idempotency_key' => $idempotencyKey,
                'payload' => $normalizedPayload,
                'status' => PaymentWebhookReceiptStatus::RECEIVED,
            ]);
            $receipt->markAsProcessing();
            $response = $next($request);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Request already processed'], 409);
        } catch (Throwable $exception) {
            $receipt?->markAsFailed($exception->getMessage());

            throw $exception;
        } finally {
            Redis::eval(File::get(resource_path('lua/release_redis_lock.lua')), 1, $lockKey, $lockOwner);
        }

        if ($response->isClientError() || $response->isServerError()) {
            $receipt->markAsFailed(sprintf(
                'Request returned HTTP status %d.',
                $response->getStatusCode(),
            ));

            return $response;
        }

        $receipt->markAsProcessed();

        return $response;
    }
}
