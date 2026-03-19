<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class RefundExceedsPaymentAmountException extends \DomainException
{
    public function __construct(PaymentId $paymentId, Money $requested, Money $available)
    {
        parent::__construct(sprintf(
            'Refund of %s %s exceeds the refundable amount of %s %s for payment "%s".',
            $requested->toDecimalString(),
            $requested->getCurrency()->code,
            $available->toDecimalString(),
            $available->getCurrency()->code,
            $paymentId->value
        ));
    }
}
