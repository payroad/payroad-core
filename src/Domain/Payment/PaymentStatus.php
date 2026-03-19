<?php

namespace Payroad\Domain\Payment;

enum PaymentStatus: string
{
    case PENDING            = 'pending';
    case PROCESSING         = 'processing';
    case SUCCEEDED          = 'succeeded';
    case FAILED             = 'failed';
    case CANCELED           = 'canceled';
    case EXPIRED            = 'expired';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED           = 'refunded';

    /**
     * Returns true if the payment can no longer accept new payment attempts.
     *
     * PARTIALLY_REFUNDED is included because the underlying charge has already
     * succeeded — the payment is closed for new attempts even though money
     * continues to be returned to the customer incrementally.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::CANCELED,
            self::EXPIRED, self::PARTIALLY_REFUNDED, self::REFUNDED => true,
            default => false,
        };
    }

    public function isRefundable(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::PARTIALLY_REFUNDED => true,
            default                                   => false,
        };
    }
}
