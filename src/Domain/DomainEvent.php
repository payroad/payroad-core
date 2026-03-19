<?php

namespace Payroad\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    /** Unique identifier for this event instance — used for idempotency and deduplication. */
    public function eventId(): string;

    public function occurredOn(): DateTimeImmutable;
}
