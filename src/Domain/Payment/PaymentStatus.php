<?php

namespace Payroad\Domain\Payment;

enum PaymentStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case SUCCEEDED  = 'succeeded';
    case FAILED     = 'failed';
    case CANCELED   = 'canceled';
    case EXPIRED    = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::CANCELED, self::EXPIRED => true,
            default => false,
        };
    }
}
