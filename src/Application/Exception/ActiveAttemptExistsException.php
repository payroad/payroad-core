<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\Payment\PaymentId;

final class ActiveAttemptExistsException extends \DomainException
{
    public function __construct(PaymentId $paymentId)
    {
        parent::__construct(
            "Payment \"{$paymentId->value}\" already has an active (non-terminal) attempt. " .
            "Wait for the current attempt to resolve before initiating a new one."
        );
    }
}
