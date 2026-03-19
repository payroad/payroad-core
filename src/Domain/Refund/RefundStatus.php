<?php

namespace Payroad\Domain\Refund;

enum RefundStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case SUCCEEDED  = 'succeeded';
    case FAILED     = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED => true,
            default                       => false,
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCEEDED;
    }

    public function isFailure(): bool
    {
        return $this === self::FAILED;
    }
}
