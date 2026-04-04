<?php

namespace Payroad\Application\UseCase\Cash;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Cash\CashAttemptContext;

final readonly class InitiateCashAttemptCommand
{
    public function __construct(
        public PaymentAttemptId   $attemptId,
        public PaymentId          $paymentId,
        public string             $providerName,
        public CashAttemptContext $context,
    ) {}
}
