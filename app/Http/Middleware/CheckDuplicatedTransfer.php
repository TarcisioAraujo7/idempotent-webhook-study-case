<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckDuplicatedTransfer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = static::generateIdempotencyKey($request->toArray());

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

    /**
     * @param array<mixed> $payload
     * @return string
     */
    public static function generateIdempotencyKey(array $payload): string
    {
        $flattenedPayload = Arr::dot($payload);
        ksort($flattenedPayload);

        $encodedPayload = json_encode($flattenedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'webhook:idempotency:' . hash('sha256', $encodedPayload ?: '[]');
    }
}
