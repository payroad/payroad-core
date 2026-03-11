<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\Payment\PaymentId;

final class PaymentNotFoundException extends \RuntimeException
{
    public function __construct(PaymentId $id)
    {
        parent::__construct("Payment \"{$id->value}\" not found.");
    }
}
