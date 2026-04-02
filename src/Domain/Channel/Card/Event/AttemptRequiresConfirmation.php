<?php

namespace Payroad\Domain\Channel\Card\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a card attempt transitions to AWAITING_CONFIRMATION.
 * The application layer must redirect the customer to a 3DS challenge
 * or bank authentication page before the payment can proceed.
 */
final readonly class AttemptRequiresConfirmation extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId $attemptId,
        public PaymentId        $paymentId,
    ) {
        parent::__construct();
    }
}
