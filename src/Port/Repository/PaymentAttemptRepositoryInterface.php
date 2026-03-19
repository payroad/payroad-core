<?php

namespace Payroad\Port\Repository;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Payment\PaymentId;

interface PaymentAttemptRepositoryInterface
{
    /**
     * Generates the next available AttemptId without persisting anything.
     *
     * UUID implementation:  returns PaymentAttemptId::generate()
     * Auto-increment impl:  uses a DB sequence or inserts into an ID table
     *
     * Always call this before creating a typed attempt to ensure the ID is known upfront.
     */
    public function nextId(): PaymentAttemptId;

    /**
     * Persists the attempt aggregate.
     *
     * Implementation must:
     * 1. Use attempt->getVersion() in the WHERE clause for optimistic locking.
     * 2. Call attempt->incrementVersion() after a successful write.
     *
     * @throws \RuntimeException on optimistic lock conflict (concurrent modification).
     */
    public function save(PaymentAttempt $attempt): void;

    public function findById(PaymentAttemptId $id): ?PaymentAttempt;

    /**
     * Primary lookup path for incoming webhooks.
     * The combination of providerName + providerReference must be unique.
     */
    public function findByProviderReference(string $providerName, string $reference): ?PaymentAttempt;

    /** @return PaymentAttempt[] */
    public function findByPaymentId(PaymentId $paymentId): array;
}
