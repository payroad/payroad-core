<?php

namespace Payroad\Application\UseCase\Crypto;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Crypto\CryptoAttemptContext;

final readonly class InitiateCryptoAttemptCommand
{
    public function __construct(
        public PaymentAttemptId     $attemptId,
        public PaymentId            $paymentId,
        public string               $providerName,
        public CryptoAttemptContext $context,
    ) {}
}
