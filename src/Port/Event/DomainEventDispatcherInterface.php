<?php

namespace Payroad\Port\Event;

use Payroad\Domain\DomainEvent;

interface DomainEventDispatcherInterface
{
    public function dispatch(DomainEvent ...$events): void;
}
