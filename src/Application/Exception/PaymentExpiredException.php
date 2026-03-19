<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\Payment\PaymentId;

final class PaymentExpiredException extends \RuntimeException
{
    public function __construct(PaymentId $id)
    {
        parent::__construct("Payment \"{$id->value}\" has expired.");
    }
}
