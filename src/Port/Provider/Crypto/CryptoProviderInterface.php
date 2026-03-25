<?php

namespace Payroad\Port\Provider\Crypto;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentFlow\Crypto\CryptoPaymentAttempt;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Provider\PaymentProviderInterface;

/**
 * Base interface for all crypto payment providers.
 *
 * Programmatic refund support is optional — providers that support it
 * also implement RefundableCryptoProviderInterface.
 */
interface CryptoProviderInterface extends PaymentProviderInterface
{
    /**
     * Creates a crypto attempt and returns a deposit wallet address.
     * The context specifies the network and optional memo.
     */
    public function initiateCryptoAttempt(
        PaymentAttemptId     $id,
        PaymentId            $paymentId,
        string               $providerName,
        Money                $amount,
        CryptoAttemptContext $context
    ): CryptoPaymentAttempt;
}
