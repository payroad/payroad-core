<?php

namespace Payroad\Port\Repository;

use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;

interface PaymentRepositoryInterface
{
    /**
     * Generates the next available PaymentId without persisting anything.
     *
     * UUID implementation:  returns PaymentId::generate()
     * Auto-increment impl:  uses a DB sequence or inserts into an ID table
     *
     * Always call this before Payment::create() to ensure the ID is known upfront.
     */
    public function nextId(): PaymentId;

    /**
     * Persists the payment aggregate.
     *
     * Implementation must:
     * 1. Use payment->getVersion() in the WHERE clause for optimistic locking.
     * 2. Call payment->incrementVersion() after a successful write.
     *
     * @throws \RuntimeException on optimistic lock conflict (concurrent modification).
     */
    public function save(Payment $payment): void;

    public function findById(PaymentId $id): ?Payment;
}
