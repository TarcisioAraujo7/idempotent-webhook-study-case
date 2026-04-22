<?php

namespace App\Models;

use App\Enums\PaymentWebhookReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PaymentWebhookReceipt extends Model
{
    protected $fillable = [
        'idempotency_key',
        'payload',
        'status',
        'processed_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'status' => PaymentWebhookReceiptStatus::class,
        'payload' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function markAsProcessing(): void
    {
        $this->transitionTo(PaymentWebhookReceiptStatus::PROCESSING);
    }

    public function markAsProcessed(): void
    {
        $this->transitionTo(PaymentWebhookReceiptStatus::PROCESSED, [
            'processed_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->transitionTo(PaymentWebhookReceiptStatus::FAILED, [
            'failed_at' => now(),
            'failure_reason' => $this->normalizeFailureReason($reason),
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function transitionTo(PaymentWebhookReceiptStatus $nextStatus, array $attributes = []): void
    {
        /** @var PaymentWebhookReceiptStatus $currentStatus */
        $currentStatus = $this->status;

        if (! $currentStatus->canTransitionTo($nextStatus)) {
            throw new LogicException(sprintf(
                'Invalid status transition from [%s] to [%s].',
                $currentStatus->value,
                $nextStatus->value,
            ));
        }

        $this->forceFill(array_merge($attributes, [
            'status' => $nextStatus,
        ]))->save();
    }

    private function normalizeFailureReason(?string $reason): ?string
    {
        $normalizedReason = trim((string) $reason);

        if ($normalizedReason === '') {
            return null;
        }

        return substr($normalizedReason, 0, 1000);
    }
}
