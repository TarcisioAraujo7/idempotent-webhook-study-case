<?php

namespace App\Services;

final class PaymentPayloadNormalizer
{
    /**
     * @param array<mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $amountInCents = $payload['amount_in_cents'] ?? null;

        if (is_numeric($amountInCents)) {
            $amountInCents = (int) $amountInCents;
        }

        return [
            'payer_name' => $this->normalizeName((string) ($payload['payer_name'] ?? '')),
            'payer_document' => $this->normalizeDocument((string) ($payload['payer_document'] ?? '')),
            'amount_in_cents' => $amountInCents,
            'bank_code' => strtoupper(trim((string) ($payload['bank_code'] ?? ''))),
            'branch_number' => trim((string) ($payload['branch_number'] ?? '')),
            'account_number' => trim((string) ($payload['account_number'] ?? '')),
        ];
    }

    private function normalizeName(string $name): string
    {
        $normalizedName = trim($name);

        return preg_replace('/\s+/', ' ', $normalizedName) ?? $normalizedName;
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D+/', '', $document) ?? $document;
    }
}
