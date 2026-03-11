<?php

namespace Payroad\Application\UseCase\CreatePayment;

use DateTimeImmutable;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\PaymentMetadata;

final readonly class CreatePaymentCommand
{
    public function __construct(
        public Money           $amount,
        public MerchantId      $merchantId,
        public CustomerId      $customerId,
        public IdempotencyKey  $idempotencyKey,
        public PaymentMetadata $metadata  = new PaymentMetadata(),
        public ?DateTimeImmutable $expiresAt = null
    ) {}
}
