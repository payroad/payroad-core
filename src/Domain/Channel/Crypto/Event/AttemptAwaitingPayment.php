<?php

namespace Payroad\Domain\Channel\Crypto\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a crypto attempt transitions to AWAITING_CONFIRMATION.
 * The customer must send the crypto payment to the provider address.
 * The attempt will transition to SUCCEEDED or EXPIRED once the provider
 * detects and confirms the on-chain transaction.
 */
final readonly class AttemptAwaitingPayment extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId $attemptId,
        public PaymentId        $paymentId,
    ) {
        parent::__construct();
    }
}
