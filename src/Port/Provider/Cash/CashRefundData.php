<?php

namespace Payroad\Port\Provider\Cash;

use Payroad\Port\Provider\RefundData;

/**
 * Data interface for cash-based refunds.
 * Implementations live in provider packages.
 */
interface CashRefundData extends RefundData
{
    /** Voucher or authorization code issued to the customer for cash pickup. */
    public function getRefundVoucherCode(): ?string;

    /** Location or terminal where the cash refund can be collected. */
    public function getPickupLocation(): ?string;
}
