<?php

namespace Payroad\Port\Repository;

use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\Refund;
use Payroad\Domain\Refund\RefundId;

interface RefundRepositoryInterface
{
    /**
     * Generates the next available RefundId without persisting anything.
     *
     * UUID implementation:  returns RefundId::generate()
     * Auto-increment impl:  uses a DB sequence or inserts into an ID table
     */
    public function nextId(): RefundId;

    /**
     * Persists the refund aggregate.
     *
     * Implementation must:
     * 1. Use refund->getVersion() in the WHERE clause for optimistic locking.
     * 2. Call refund->incrementVersion() after a successful write.
     *
     * @throws \RuntimeException on optimistic lock conflict (concurrent modification).
     */
    public function save(Refund $refund): void;

    public function findById(RefundId $id): ?Refund;

    /**
     * Primary lookup path for incoming refund webhooks.
     * The combination of providerName + providerReference must be unique.
     */
    public function findByProviderReference(string $providerName, string $reference): ?Refund;

    /** @return Refund[] */
    public function findByPaymentId(PaymentId $paymentId): array;
}
