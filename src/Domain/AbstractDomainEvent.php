<?php

namespace Payroad\Domain;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Base class for all domain events.
 * Generates a unique eventId (UUID v4) and captures occurredOn automatically.
 * Subclasses declare only their payload properties and call parent::__construct().
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    public readonly string $eventId;
    public readonly DateTimeImmutable $occurredOn;

    protected function __construct()
    {
        $this->eventId    = Uuid::uuid4()->toString();
        $this->occurredOn = new DateTimeImmutable();
    }

    public function eventId(): string              { return $this->eventId; }
    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
