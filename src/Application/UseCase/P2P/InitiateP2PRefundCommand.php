<?php

namespace Payroad\Application\UseCase\P2P;

use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\P2P\P2PRefundContext;

final readonly class InitiateP2PRefundCommand
{
    public function __construct(
        public PaymentId      $paymentId,
        public Money          $amount,
        public P2PRefundContext $context = new P2PRefundContext(),
    ) {}
}
