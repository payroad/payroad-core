<?php

namespace Payroad\Domain\Channel\Cash\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a cash attempt transitions to AWAITING_CONFIRMATION.
 * The customer must present the payment code at a physical cash terminal
 * or ATM. The attempt will transition to SUCCEEDED or EXPIRED once the
 * provider registers the cash deposit.
 */
final readonly class AttemptAwaitingCashPayment extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId $attemptId,
        public PaymentId        $paymentId,
    ) {
        parent::__construct();
    }
}
