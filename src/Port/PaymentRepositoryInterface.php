<?php

namespace Payroad\Port;

use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findById(PaymentId $id): ?Payment;

    /** Used by the CreatePayment use case for idempotency check. */
    public function findByIdempotencyKey(IdempotencyKey $key): ?Payment;
}
