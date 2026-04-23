<?php

namespace App\Services;

use Illuminate\Http\Request;

final class PaymentWebhookIdempotencyKeyResolver
{
    public function resolve(Request $request): ?string
    {
        $providerIdempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

        if ($providerIdempotencyKey === '') {
            $providerIdempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        }

        return 'webhook:provider-idempotency:' . hash('sha256', $providerIdempotencyKey);
    }
}
