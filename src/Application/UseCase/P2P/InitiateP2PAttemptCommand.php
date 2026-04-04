<?php

namespace Payroad\Application\UseCase\P2P;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\P2P\P2PAttemptContext;

final readonly class InitiateP2PAttemptCommand
{
    public function __construct(
        public PaymentAttemptId  $attemptId,
        public PaymentId         $paymentId,
        public string            $providerName,
        public P2PAttemptContext $context,
    ) {}
}
