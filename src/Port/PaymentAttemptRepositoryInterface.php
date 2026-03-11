<?php

namespace Payroad\Port;

use Payroad\Domain\Attempt\AttemptId;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Payment\PaymentId;

interface PaymentAttemptRepositoryInterface
{
    public function save(PaymentAttempt $attempt): void;

    public function findById(AttemptId $id): ?PaymentAttempt;

    /**
     * Primary lookup path for incoming webhooks.
     * The combination of providerType + providerReference must be unique.
     */
    public function findByProviderReference(string $providerType, string $reference): ?PaymentAttempt;

    /** @return PaymentAttempt[] */
    public function findByPaymentId(PaymentId $paymentId): array;
}
