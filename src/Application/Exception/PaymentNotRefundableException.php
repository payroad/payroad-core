<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentStatus;

final class PaymentNotRefundableException extends \DomainException
{
    public function __construct(PaymentId $paymentId, PaymentStatus $status)
    {
        parent::__construct(
            "Payment \"{$paymentId->value}\" cannot be refunded in status \"{$status->value}\". " .
            "Only SUCCEEDED or PARTIALLY_REFUNDED payments are refundable."
        );
    }
}
