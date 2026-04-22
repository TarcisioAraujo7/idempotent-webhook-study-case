<?php

namespace App\Services;

final class PaymentIdempotencyKeyGenerator
{
    /**
     * @param array<string, mixed> $payload
     */
    public function generate(array $payload): string
    {
        return 'webhook:idempotency:' . hash('sha256', implode('|', [
            (string) ($payload['payer_document'] ?? ''),
            (string) ($payload['amount_in_cents'] ?? ''),
            (string) ($payload['bank_code'] ?? ''),
            (string) ($payload['branch_number'] ?? ''),
            (string) ($payload['account_number'] ?? ''),
        ]));
    }
}
