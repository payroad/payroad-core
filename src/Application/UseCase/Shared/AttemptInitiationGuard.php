<?php

namespace Payroad\Application\UseCase\Shared;

use Payroad\Application\Exception\ActiveAttemptExistsException;
use Payroad\Application\Exception\PaymentExpiredException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

/**
 * Shared pre-conditions for all per-flow initiation use cases.
 * Eliminates duplication of payment validation and active-attempt guard.
 *
 * Note: each flow (Card, Crypto, P2P, Cash) has its own use case class rather than a generic
 * base class. This is intentional — PHP does not support generic type parameters, so a single
 * InitiateAttemptUseCase<T> cannot exist without losing static type safety on the return type.
 * The four classes are structurally similar; this guard captures their shared pre-conditions.
 */
final class AttemptInitiationGuard
{
    public function __construct(
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
    ) {}

    /**
     * Loads and validates the payment for attempt initiation.
     *
     * Guards are read-only: they never save state or dispatch events.
     * The calling use case is responsible for persisting expiry transitions if needed.
     *
     * @throws PaymentNotFoundException
     * @throws PaymentExpiredException if payment's TTL has passed (caller should persist expiry via ExpirePaymentUseCase)
     * @throws \DomainException if payment is in a terminal status
     */
    public function loadPayment(PaymentId $paymentId): Payment
    {
        $payment = $this->payments->findById($paymentId)
            ?? throw new PaymentNotFoundException($paymentId);

        if ($payment->isExpired()) {
            throw new PaymentExpiredException($paymentId);
        }

        if ($payment->getStatus()->isTerminal()) {
            throw new \DomainException(
                "Cannot initiate attempt on a terminal payment (status: {$payment->getStatus()->value})."
            );
        }

        return $payment;
    }

    /**
     * @throws ActiveAttemptExistsException if a non-terminal attempt already exists for this payment
     */
    public function guardNoActiveAttempt(PaymentId $paymentId): void
    {
        $active = array_filter(
            $this->attempts->findByPaymentId($paymentId),
            fn(PaymentAttempt $a) => !$a->getStatus()->isTerminal()
        );

        if (count($active) > 0) {
            throw new ActiveAttemptExistsException($paymentId);
        }
    }
}
