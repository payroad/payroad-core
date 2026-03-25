<?php

namespace Payroad\Application\UseCase\Shared;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\Exception\PaymentNotRefundableException;
use Payroad\Application\Exception\RefundExceedsPaymentAmountException;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Port\Repository\PaymentRepositoryInterface;

/**
 * Shared pre-conditions for all per-flow refund initiation use cases.
 * Eliminates duplication of payment validation and refund amount guard.
 *
 * Note: each flow (Card, Crypto, P2P, Cash) has its own use case class rather than a generic
 * base class. This is intentional — PHP does not support generic type parameters, so a single
 * InitiateRefundUseCase<T> cannot exist without losing static type safety on the return type.
 * The four classes are structurally similar; this guard captures their shared pre-conditions.
 *
 * Each refund use case performs an instanceof check on the successful PaymentAttempt to confirm
 * the attempt belongs to the same flow. This is a data-consistency guard, not a design smell:
 * the payment aggregate records the attempt ID, but the repository returns the abstract type.
 */
final class RefundInitiationGuard
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
    ) {}

    /**
     * Loads and validates the payment for refund initiation.
     *
     * @throws PaymentNotFoundException
     * @throws PaymentNotRefundableException if the payment is not in a refundable status
     * @throws RefundExceedsPaymentAmountException if the requested amount exceeds the refundable balance
     */
    public function loadRefundablePayment(PaymentId $paymentId, Money $amount): Payment
    {
        $payment = $this->payments->findById($paymentId)
            ?? throw new PaymentNotFoundException($paymentId);

        try {
            $payment->assertCanInitiateRefund($amount);
        } catch (\DomainException) {
            if (!$payment->getStatus()->isRefundable()) {
                throw new PaymentNotRefundableException($paymentId, $payment->getStatus());
            }
            throw new RefundExceedsPaymentAmountException(
                $paymentId,
                $amount,
                $payment->getRefundableAmount()
            );
        }

        return $payment;
    }
}
