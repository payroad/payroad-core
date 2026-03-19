<?php

namespace Tests\Stub;

use Payroad\Domain\PaymentFlow\Cash\CashAttemptData;

final class StubCashData implements CashAttemptData
{
    public function getDepositCode(): string     { return 'STUB-1234'; }
    public function getDepositLocation(): string { return 'Stub Terminal'; }
}
