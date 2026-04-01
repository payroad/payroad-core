<?php

namespace Payroad\Domain\PaymentFlow\Crypto;

use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * State machine for the generic crypto payment flow.
 * Intermediate confirmation updates (confirmations: 3/6) do not change
 * AttemptStatus — they only update SpecificData via the provider.
 *
 * Standard flow (NOWPayments, CoinGate):
 *   PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
 *                                     └──► SUCCEEDED    └──► FAILED
 *                                     └──► FAILED       └──► EXPIRED
 *                                     └──► EXPIRED
 *                                     └──► CANCELED
 *
 * Direct PENDING → SUCCEEDED is allowed for providers that confirm instantly
 * without an intermediate status (e.g. CoinGate "paid" in sandbox).
 *
 * Underpayment (NOWPayments "partially_paid"):
 *   AWAITING_CONFIRMATION ──► PARTIALLY_PAID ──► SUCCEEDED  (customer topped up)
 *   PENDING               ──► PARTIALLY_PAID               (legacy / direct underpayment)
 *                                              └──► EXPIRED
 *                                              └──► FAILED
 */
final class CryptoStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(AttemptStatus $from, AttemptStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            AttemptStatus::PENDING => in_array($to, [
                AttemptStatus::AWAITING_CONFIRMATION,
                AttemptStatus::PROCESSING,
                AttemptStatus::PARTIALLY_PAID,
                AttemptStatus::SUCCEEDED,
                AttemptStatus::FAILED,
                AttemptStatus::EXPIRED,
                AttemptStatus::CANCELED,
            ], true),

            AttemptStatus::AWAITING_CONFIRMATION => in_array($to, [
                AttemptStatus::PROCESSING,
                AttemptStatus::PARTIALLY_PAID,
                AttemptStatus::SUCCEEDED,
                AttemptStatus::FAILED,
                AttemptStatus::EXPIRED,
                AttemptStatus::CANCELED,
            ], true),

            AttemptStatus::PARTIALLY_PAID => in_array($to, [
                AttemptStatus::SUCCEEDED,
                AttemptStatus::EXPIRED,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::PROCESSING => in_array($to, [
                AttemptStatus::SUCCEEDED,
                AttemptStatus::FAILED,
                AttemptStatus::EXPIRED,
            ], true),

            default => false,
        };
    }
}
