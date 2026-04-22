<?php

namespace App\Http\Middleware;

use App\Services\PaymentIdempotencyKeyGenerator;
use App\Services\PaymentPayloadNormalizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckDuplicatedTransfer
{
    public function __construct(
        private readonly PaymentPayloadNormalizer $payloadNormalizer,
        private readonly PaymentIdempotencyKeyGenerator $idempotencyKeyGenerator,
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

        $idempotencyKey = $this->idempotencyKeyGenerator->generate($normalizedPayload);

        if (! Redis::set($idempotencyKey, 1, 'EX', 30, 'NX')) {
            return response()->json(['message' => 'Request already processed'], 409);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Redis::del($idempotencyKey);

            throw $exception;
        }

        if ($response->isClientError() || $response->isServerError()) {
            Redis::del($idempotencyKey);
        }

        return $response;
    }
}
