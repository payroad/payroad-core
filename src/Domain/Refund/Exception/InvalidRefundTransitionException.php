<?php

namespace Payroad\Domain\Refund\Exception;

use Payroad\Domain\Refund\RefundStatus;

final class InvalidRefundTransitionException extends \RuntimeException
{
    public function __construct(RefundStatus $from, RefundStatus $to)
    {
        parent::__construct(sprintf(
            'Invalid refund transition from "%s" to "%s".',
            $from->value,
            $to->value
        ));
    }
}
