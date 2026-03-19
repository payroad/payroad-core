<?php

namespace Payroad\Application\UseCase\Cash;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Shared\RefundInitiationGuard;
use Payroad\Domain\PaymentFlow\Cash\CashPaymentAttempt;
use Payroad\Domain\PaymentFlow\Cash\CashRefund;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;

final class InitiateCashRefundUseCase
{
    public function __construct(
        private RefundInitiationGuard             $guard,
        private PaymentAttemptRepositoryInterface $attempts,
        private RefundRepositoryInterface         $refunds,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateCashRefundCommand $command): CashRefund
    {
        $payment = $this->guard->loadRefundablePayment($command->paymentId, $command->amount);

        $attemptId = $payment->getSuccessfulAttemptId()
            ?? throw new \LogicException("Payment has no successful attempt to refund against.");

        $attempt = $this->attempts->findById($attemptId)
            ?? throw new AttemptNotFoundException($attemptId);

        // Data-consistency guard: the repository returns the abstract PaymentAttempt type.
        // We must confirm the successful attempt belongs to the same flow before proceeding.
        if (!$attempt instanceof CashPaymentAttempt) {
            throw new \LogicException(
                "Expected CashPaymentAttempt for payment \"{$command->paymentId->value}\", got " . get_class($attempt) . '.'
            );
        }

        $id     = $this->refunds->nextId();
        $refund = $this->providers
            ->forCash($attempt->getProviderName())
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
