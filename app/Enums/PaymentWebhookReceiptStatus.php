<?php

namespace App\Enums;

enum PaymentWebhookReceiptStatus: string
{
    case RECEIVED = 'received';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function canTransitionTo(self $nextStatus): bool
    {
        return match ($this) {
            self::RECEIVED => in_array($nextStatus, [self::PROCESSING, self::FAILED], true),
            self::PROCESSING => in_array($nextStatus, [self::PROCESSED, self::FAILED], true),
            self::PROCESSED, self::FAILED => false,
        };
    }

    public function duplicateMessage(): string
    {
        return match ($this) {
            self::RECEIVED,
            self::PROCESSING => 'Request is already being processed.',
            self::PROCESSED => 'Request already processed.',
            self::FAILED => 'Request already received and failed previously.',
        };
    }
}
