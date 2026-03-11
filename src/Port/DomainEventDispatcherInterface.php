<?php

namespace Payroad\Port;

use Payroad\Domain\Event\DomainEvent;

interface DomainEventDispatcherInterface
{
    public function dispatch(DomainEvent ...$events): void;
}
