<?php

namespace Payroad\Domain\Channel\Cash;

use Payroad\Domain\Attempt\AttemptData;

/**
 * Data interface for cash payment attempts (terminal, payment kiosk, etc.).
 * Implementations live in provider packages (e.g. payroad/kuaipay-provider).
 */
interface CashAttemptData extends AttemptData
{
    /** Unique code the customer presents at the cash point. */
    public function getDepositCode(): string;

    /** Human-readable location or service name where the deposit can be made. */
    public function getDepositLocation(): string;
}
