<?php

namespace Tests\Stub;

use Payroad\Port\Provider\P2P\P2PRefundData;

final class StubP2PRefundData implements P2PRefundData
{
    public function getReturnTransferReference(): ?string { return 'REF-STUB-001'; }
    public function getReason(): ?string                  { return 'requested_by_customer'; }
}
