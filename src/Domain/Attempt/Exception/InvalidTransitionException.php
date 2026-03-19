<?php

namespace Payroad\Domain\Attempt\Exception;

use Payroad\Domain\Attempt\AttemptStatus;

final class InvalidTransitionException extends \RuntimeException
{
    public function __construct(AttemptStatus $from, AttemptStatus $to, string $methodType)
    {
        parent::__construct(sprintf(
            'Invalid attempt transition from "%s" to "%s" for method type "%s".',
            $from->value,
            $to->value,
            $methodType
        ));
    }
}
