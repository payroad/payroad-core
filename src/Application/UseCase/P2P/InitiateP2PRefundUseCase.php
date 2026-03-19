<?php

namespace Payroad\Application\UseCase\P2P;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Shared\RefundInitiationGuard;
use Payroad\Domain\PaymentFlow\P2P\P2PPaymentAttempt;
use Payroad\Domain\PaymentFlow\P2P\P2PRefund;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;

final class InitiateP2PRefundUseCase
{
    public function __construct(
        private RefundInitiationGuard             $guard,
        private PaymentAttemptRepositoryInterface $attempts,
        private RefundRepositoryInterface         $refunds,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateP2PRefundCommand $command): P2PRefund
    {
        $payment = $this->guard->loadRefundablePayment($command->paymentId, $command->amount);

        $attemptId = $payment->getSuccessfulAttemptId()
            ?? throw new \LogicException("Payment has no successful attempt to refund against.");

        $attempt = $this->attempts->findById($attemptId)
            ?? throw new AttemptNotFoundException($attemptId);

        // Data-consistency guard: the repository returns the abstract PaymentAttempt type.
        // We must confirm the successful attempt belongs to the same flow before proceeding.
        if (!$attempt instanceof P2PPaymentAttempt) {
            throw new \LogicException(
                "Expected P2PPaymentAttempt for payment \"{$command->paymentId->value}\", got " . get_class($attempt) . '.'
            );
        }

        $id     = $this->refunds->nextId();
        $refund = $this->providers
            ->forP2P($attempt->getProviderName())
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
