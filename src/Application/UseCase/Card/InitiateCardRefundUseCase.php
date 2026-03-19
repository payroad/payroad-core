<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Shared\RefundInitiationGuard;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\PaymentFlow\Card\CardRefund;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;

final class InitiateCardRefundUseCase
{
    public function __construct(
        private RefundInitiationGuard             $guard,
        private PaymentAttemptRepositoryInterface $attempts,
        private RefundRepositoryInterface         $refunds,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateCardRefundCommand $command): CardRefund
    {
        $payment = $this->guard->loadRefundablePayment($command->paymentId, $command->amount);

        $attemptId = $payment->getSuccessfulAttemptId()
            ?? throw new \LogicException("Payment has no successful attempt to refund against.");

        $attempt = $this->attempts->findById($attemptId)
            ?? throw new AttemptNotFoundException($attemptId);

        // Data-consistency guard: the repository returns the abstract PaymentAttempt type.
        // We must confirm the successful attempt belongs to the same flow before proceeding.
        if (!$attempt instanceof CardPaymentAttempt) {
            throw new \LogicException(
                "Expected CardPaymentAttempt for payment \"{$command->paymentId->value}\", got " . get_class($attempt) . '.'
            );
        }

        $id     = $this->refunds->nextId();
        $refund = $this->providers
            ->forCard($attempt->getProviderName())
            ->initiateRefund(
                $id,
                $payment->getId(),
                $attemptId,
                $attempt->getProviderName(),
                $command->amount,
                $attempt->getProviderReference()
                    ?? throw new \DomainException(
                        "Attempt \"{$attemptId->value}\" has no provider reference — cannot initiate refund."
                    ),
                $command->context,
            );

        $this->refunds->save($refund);
        $this->dispatcher->dispatch(...$refund->releaseEvents());

        return $refund;
    }

}
