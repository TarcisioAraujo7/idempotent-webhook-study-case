<?php

namespace App\Http\Middleware;

use App\Enums\PaymentWebhookReceiptStatus;
use App\Models\PaymentWebhookReceipt;
use App\Services\PaymentPayloadNormalizer;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckDuplicatedTransferInDatabase
{
    public function __construct(
        private readonly PaymentPayloadNormalizer $payloadNormalizer,
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

        $idempotencyKey = $this->resolveIdempotencyKey($request);

        if ($idempotencyKey === null) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
            ], 400);
        }

        try {
            $receipt = PaymentWebhookReceipt::query()->create([
                'idempotency_key' => $idempotencyKey,
                'payload' => $normalizedPayload,
                'status' => PaymentWebhookReceiptStatus::RECEIVED,
            ]);
        } catch (UniqueConstraintViolationException) {
            /** @var PaymentWebhookReceipt $receipt */
            $receipt = PaymentWebhookReceipt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            $status = $receipt->status;

            return response()->json([
                'message' => $status->duplicateMessage(),
                'status' => $status->value,
            ], 409);
        }

        $receipt->markAsProcessing();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $receipt->markAsFailed($exception->getMessage());

            throw $exception;
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

    private function resolveIdempotencyKey(Request $request): string
    {
        $providerIdempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

        if ($providerIdempotencyKey === '') {
            $providerIdempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        }

        return 'webhook:provider-idempotency:' . hash('sha256', $providerIdempotencyKey);
    }
}
