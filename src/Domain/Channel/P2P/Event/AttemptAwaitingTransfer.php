<?php

namespace Payroad\Domain\Channel\P2P\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a P2P attempt transitions to AWAITING_CONFIRMATION.
 * The customer must complete the bank or wallet transfer to the provider
 * account. The attempt will transition to SUCCEEDED, EXPIRED, or CANCELED
 * once the provider confirms or times out the transfer.
 */
final readonly class AttemptAwaitingTransfer extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId $attemptId,
        public PaymentId        $paymentId,
    ) {
        parent::__construct();
    }
}
