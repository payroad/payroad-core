<?php

namespace Payroad\Domain;

/**
 * Common infrastructure for all aggregate roots.
 * Provides event recording, event releasing, and optimistic-lock versioning.
 *
 * Usage: add `use AggregateRootTrait;` inside the aggregate class body.
 */
trait AggregateRootTrait
{
    private int   $version        = 0;
    private array $recordedEvents = [];

    protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return DomainEvent[] */
    public function releaseEvents(): array
    {
        $events               = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    /**
     * Called by the repository after a successful persist.
     * Keeps the in-memory aggregate in sync with the stored version
     * so the next save can use the correct optimistic-lock value.
     *
     * @internal — repository use only.
     */
    public function incrementVersion(): void
    {
        $this->version++;
    }

    /**
     * Sets the version directly — used by repositories when reconstituting an aggregate from storage.
     * Never call this in business logic.
     *
     * @internal — repository use only.
     */
    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
