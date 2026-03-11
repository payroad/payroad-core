<?php

namespace Payroad\Port;

use Payroad\Domain\Attempt\StateMachine\AttemptStateMachineInterface;
use Payroad\Domain\Payment\PaymentMethodType;

interface StateMachineRegistryInterface
{
    /** @throws \Payroad\Application\Exception\ProviderNotFoundException */
    public function getByMethodType(PaymentMethodType $type): AttemptStateMachineInterface;
}
