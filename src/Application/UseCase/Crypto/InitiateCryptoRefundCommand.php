<?php

namespace Payroad\Application\UseCase\Crypto;

use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Crypto\CryptoRefundContext;

final readonly class InitiateCryptoRefundCommand
{
    public function __construct(
        public PaymentId          $paymentId,
        public Money              $amount,
        public CryptoRefundContext $context,
    ) {}
}
