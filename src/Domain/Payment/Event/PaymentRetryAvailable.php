<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a payment transitions from PROCESSING back to PENDING,
 * meaning a new payment attempt may now be initiated.
 * Emitted by Payment::markRetryable() after a failed or voided attempt.
 */
final readonly class PaymentRetryAvailable extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
    ) {
        parent::__construct();
    }
}
