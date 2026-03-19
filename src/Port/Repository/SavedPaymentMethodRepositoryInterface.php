<?php

namespace Payroad\Port\Repository;

use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethod;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;

interface SavedPaymentMethodRepositoryInterface
{
    public function nextId(): SavedPaymentMethodId;

    /** @throws \Payroad\Application\Exception\OptimisticLockException on version conflict. */
    public function save(SavedPaymentMethod $method): void;

    public function findById(SavedPaymentMethodId $id): ?SavedPaymentMethod;

    /** @return SavedPaymentMethod[] */
    public function findByCustomerId(CustomerId $customerId): array;

    public function findByProviderToken(string $providerName, string $token): ?SavedPaymentMethod;
}
