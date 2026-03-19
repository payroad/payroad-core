<?php

namespace Payroad\Application\UseCase\Crypto;

use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Crypto\CryptoAttemptContext;

final readonly class InitiateCryptoAttemptCommand
{
    public function __construct(
        public PaymentId            $paymentId,
        public string               $providerName,
        public CryptoAttemptContext $context,
    ) {}
}
