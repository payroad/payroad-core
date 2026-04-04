<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;

final readonly class InitiateCardAttemptWithSavedMethodCommand
{
    public function __construct(
        public PaymentAttemptId     $attemptId,
        public PaymentId            $paymentId,
        public string               $providerName,
        public SavedPaymentMethodId $savedMethodId,
    ) {}
}
