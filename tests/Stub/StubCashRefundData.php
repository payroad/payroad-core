<?php

namespace Tests\Stub;

use Payroad\Port\Provider\Cash\CashRefundData;

final class StubCashRefundData implements CashRefundData
{
    public function getRefundVoucherCode(): ?string { return 'VOUCHER-STUB-001'; }
    public function getPickupLocation(): ?string    { return 'Stub Terminal'; }
}
