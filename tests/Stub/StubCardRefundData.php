<?php

namespace Tests\Stub;

use Payroad\Port\Provider\Card\CardRefundData;

final class StubCardRefundData implements CardRefundData
{
    public function getReason(): ?string                    { return 'requested_by_customer'; }
    public function getAcquirerReferenceNumber(): ?string   { return null; }
}
