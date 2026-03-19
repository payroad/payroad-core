<?php

namespace Payroad\Domain\SavedPaymentMethod;

enum SavedPaymentMethodStatus: string
{
    case ACTIVE  = 'active';
    case EXPIRED = 'expired';
    case REMOVED = 'removed';

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }
}
