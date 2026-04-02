<?php

namespace Payroad\Port\Provider\Crypto;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Channel\Crypto\CryptoRefund;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;

/**
 * Extended interface for crypto providers that support programmatic refunds.
 *
 * Not all crypto providers offer a refund API (e.g. CoinGate requires manual
 * processing via the dashboard). Providers that do support it implement this
 * interface in addition to CryptoProviderInterface.
 *
 * Use-case layer checks: instanceof RefundableCryptoProviderInterface before
 * calling initiateRefund(), and returns a user-facing error if not supported.
 */
interface RefundableCryptoProviderInterface extends CryptoProviderInterface
{
    public function initiateRefund(
        RefundId            $id,
        PaymentId           $paymentId,
        PaymentAttemptId    $originalAttemptId,
        string              $providerName,
        Money               $amount,
        string              $originalProviderReference,
        CryptoRefundContext $context
    ): CryptoRefund;
}
