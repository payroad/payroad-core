<?php

namespace Payroad\Domain\Attempt\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a payment attempt transitions to AWAITING_CONFIRMATION.
 * The application layer must redirect or present the user with a 3DS challenge,
 * bank redirect, P2P transfer instructions, or cash deposit details.
 * Subscribers use this event to trigger notification and redirect logic.
 */
final readonly class AttemptRequiresUserAction extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId  $attemptId,
        public PaymentId         $paymentId,
        public PaymentMethodType $methodType,
    ) {
        parent::__construct();
    }
}
