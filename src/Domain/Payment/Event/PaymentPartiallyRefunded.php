<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;

final readonly class PaymentPartiallyRefunded extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
        public RefundId  $refundId,
        /** Amount returned in this specific refund operation. */
        public Money     $thisRefundAmount,
        /** Total amount refunded so far across all refund operations. */
        public Money     $totalRefundedAmount,
    ) {
        parent::__construct();
    }
}
