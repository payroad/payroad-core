<?php

namespace Payroad\Domain\Refund\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStatus;

final readonly class RefundStatusChanged extends AbstractDomainEvent
{
    public function __construct(
        public RefundId    $refundId,
        public PaymentId   $paymentId,
        public RefundStatus $oldStatus,
        public RefundStatus $newStatus,
        public string      $providerStatus,
    ) {
        parent::__construct();
    }
}
