<?php

namespace Payroad\Port\Provider\P2P;

use Payroad\Port\Provider\RefundData;

/**
 * Data interface for P2P-based refunds.
 * Implementations live in provider packages.
 */
interface P2PRefundData extends RefundData
{
    /** Reference of the return transfer initiated by the provider. */
    public function getReturnTransferReference(): ?string;

    /** Reason for the refund as reported by the provider. */
    public function getReason(): ?string;
}
