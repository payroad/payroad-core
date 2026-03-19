<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Card\CardRefundContext;

final readonly class InitiateCardRefundCommand
{
    public function __construct(
        public PaymentId        $paymentId,
        public Money            $amount,
        public CardRefundContext $context = new CardRefundContext(),
    ) {}
}
