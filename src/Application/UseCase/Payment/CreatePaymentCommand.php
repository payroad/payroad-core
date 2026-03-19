<?php

namespace Payroad\Application\UseCase\Payment;

use DateTimeImmutable;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;

final readonly class CreatePaymentCommand
{
    public function __construct(
        public Money               $amount,
        public CustomerId          $customerId,
        public PaymentMetadata     $metadata  = new PaymentMetadata(),
        public ?DateTimeImmutable  $expiresAt = null,
        /** Caller-supplied ID (e.g. client-generated UUID). If null, the repository assigns one. */
        public ?PaymentId          $id        = null,
    ) {}
}
