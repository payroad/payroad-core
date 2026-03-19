<?php

namespace Payroad\Domain\Attempt\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\PaymentId;

/**
 * Raised when a provider successfully reserves funds for a payment attempt.
 * The payment is not yet captured — CaptureCardAttemptUseCase must be called to complete it.
 */
final readonly class AttemptAuthorized extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId  $attemptId,
        public PaymentId         $paymentId,
        public PaymentMethodType $methodType,
    ) {
        parent::__construct();
    }
}
