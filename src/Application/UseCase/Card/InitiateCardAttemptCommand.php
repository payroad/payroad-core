<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\Card\CardAttemptContext;

final readonly class InitiateCardAttemptCommand
{
    public function __construct(
        public PaymentId          $paymentId,
        public string             $providerName,
        public CardAttemptContext $context,
    ) {}
}
