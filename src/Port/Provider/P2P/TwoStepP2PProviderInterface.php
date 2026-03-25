<?php

declare(strict_types=1);

namespace Payroad\Port\Provider\P2P;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

/**
 * Optional capability for P2P providers that require a second server-side
 * step after the user completes an external authorization flow.
 *
 * Flow:
 *   1. initiateP2PAttempt() → returns clientToken / redirect URL (AWAITING_CONFIRMATION)
 *   2. User completes external flow (e.g. Klarna widget)
 *   3. Frontend sends authorizationToken to backend
 *   4. authorizeOrder() → creates the order → providerReference updated (PROCESSING)
 *   5. Webhook → SUCCEEDED
 */
interface TwoStepP2PProviderInterface extends P2PProviderInterface
{
    /**
     * Finalizes the payment order using the token obtained from the frontend.
     * Updates the attempt providerReference to the real order/transaction ID.
     */
    public function authorizeOrder(
        PaymentAttemptId $attemptId,
        PaymentId        $paymentId,
        string           $authorizationToken,
        Money            $amount,
    ): P2POrderResult;
}
