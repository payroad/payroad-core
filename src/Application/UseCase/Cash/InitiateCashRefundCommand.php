<?php

namespace Payroad\Application\UseCase\Cash;

use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Cash\CashRefundContext;

final readonly class InitiateCashRefundCommand
{
    public function __construct(
        public PaymentId       $paymentId,
        public Money           $amount,
        public CashRefundContext $context = new CashRefundContext(),
    ) {}
}
