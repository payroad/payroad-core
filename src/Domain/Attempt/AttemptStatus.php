<?php

namespace Payroad\Domain\Attempt;

enum AttemptStatus: string
{
    /** Just created, provider not yet called. */
    case PENDING               = 'pending';

    /** Funds reserved by the provider; awaiting explicit capture. */
    case AUTHORIZED            = 'authorized';

    /** Waiting for user action: 3DS, bank redirect, transfer, cash deposit. */
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';

    /** Provider is processing; no further user action required. */
    case PROCESSING            = 'processing';

    /** Partial crypto payment received; waiting for the remainder. */
    case PARTIALLY_PAID        = 'partially_paid';

    /** Part of the authorized hold has been captured; further captures may follow. */
    case PARTIALLY_CAPTURED    = 'partially_captured';

    case SUCCEEDED             = 'succeeded';
    case FAILED                = 'failed';
    case CANCELED              = 'canceled';
    case EXPIRED               = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::CANCELED, self::EXPIRED => true,
            default => false,
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCEEDED;
    }

    public function isFailure(): bool
    {
        return match ($this) {
            self::FAILED, self::CANCELED, self::EXPIRED => true,
            default => false,
        };
    }

    public function isAuthorized(): bool
    {
        return $this === self::AUTHORIZED;
    }
}
