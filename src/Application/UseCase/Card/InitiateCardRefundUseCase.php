<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Shared\RefundInitiationGuard;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Card\CardRefund;
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

        $attemptId = $payment->getRequiredSuccessfulAttemptId();

        $attempt = CardPaymentAttempt::fromAttempt(
            $this->attempts->findById($attemptId) ?? throw new AttemptNotFoundException($attemptId)
        );

        $id     = $this->refunds->nextId();
        $refund = $this->providers
            ->forCard($attempt->getProviderName())
            ->initiateRefund(
                $id,
                $payment->getId(),
                $attemptId,
                $attempt->getProviderName(),
                $command->amount,
                $attempt->getRequiredProviderReference(),
                $command->context,
            );

        $this->refunds->save($refund);
        $this->dispatcher->dispatch(...$refund->releaseEvents());

        return $refund;
    }

}
