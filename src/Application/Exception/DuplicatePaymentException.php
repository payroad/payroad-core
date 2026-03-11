<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\Payment\IdempotencyKey;

final class DuplicatePaymentException extends \RuntimeException
{
    public function __construct(IdempotencyKey $key)
    {
        parent::__construct("Payment with idempotency key \"{$key->value}\" already exists.");
    }
}
