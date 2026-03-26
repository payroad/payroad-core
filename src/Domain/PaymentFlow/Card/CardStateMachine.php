<?php

namespace Payroad\Domain\PaymentFlow\Card;

use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * State machine for the generic card payment flow.
 *
 * Immediate charge (Stripe Variant B — no server-side confirm):
 *   PENDING ──► SUCCEEDED   (provider confirms inline, no intermediate state)
 *           └──► FAILED
 *
 * Standard charge:
 *   PENDING ──► PROCESSING ──► SUCCEEDED
 *           └──► FAILED       └──► FAILED
 *
 * Authorize + capture:
 *   PENDING ──► AUTHORIZED ──► PROCESSING ──► SUCCEEDED
 *                          └──► SUCCEEDED    └──► FAILED
 *                          └──► CANCELED
 *                          └──► FAILED
 *
 * Partial capture (Adyen, Braintree multi-capture):
 *   AUTHORIZED ──► PARTIALLY_CAPTURED ──► PARTIALLY_CAPTURED  (more partial captures)
 *                                     └──► PROCESSING          (final capture, async settlement)
 *                                     └──► SUCCEEDED           (final capture, sync settlement)
 *                                     └──► CANCELED            (void remaining hold)
 *
 * 3DS / redirect:
 *   PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
 *                                     └──► FAILED       └──► FAILED
 *                                     └──► CANCELED
 */
final class CardStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(AttemptStatus $from, AttemptStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            AttemptStatus::PENDING => in_array($to, [
                AttemptStatus::AUTHORIZED,
                AttemptStatus::AWAITING_CONFIRMATION,
                AttemptStatus::PROCESSING,
                AttemptStatus::SUCCEEDED,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::AUTHORIZED => in_array($to, [
                AttemptStatus::PARTIALLY_CAPTURED,
                AttemptStatus::PROCESSING,
                AttemptStatus::SUCCEEDED,
                AttemptStatus::CANCELED,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::PARTIALLY_CAPTURED => in_array($to, [
                AttemptStatus::PARTIALLY_CAPTURED,
                AttemptStatus::PROCESSING,
                AttemptStatus::SUCCEEDED,
                AttemptStatus::CANCELED,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::AWAITING_CONFIRMATION => in_array($to, [
                AttemptStatus::PROCESSING,
                AttemptStatus::FAILED,
                AttemptStatus::CANCELED,
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
