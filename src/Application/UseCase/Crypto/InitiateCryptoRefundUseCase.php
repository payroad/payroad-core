<?php

namespace Payroad\Application\UseCase\Crypto;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Shared\RefundInitiationGuard;
use Payroad\Domain\PaymentFlow\Crypto\CryptoPaymentAttempt;
use Payroad\Domain\PaymentFlow\Crypto\CryptoRefund;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Crypto\RefundableCryptoProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;

final class InitiateCryptoRefundUseCase
{
    public function __construct(
        private RefundInitiationGuard             $guard,
        private PaymentAttemptRepositoryInterface $attempts,
        private RefundRepositoryInterface         $refunds,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateCryptoRefundCommand $command): CryptoRefund
    {
        $payment = $this->guard->loadRefundablePayment($command->paymentId, $command->amount);

        $attemptId = $payment->getRequiredSuccessfulAttemptId();

        $attempt = CryptoPaymentAttempt::fromAttempt(
            $this->attempts->findById($attemptId) ?? throw new AttemptNotFoundException($attemptId)
        );

        $provider = $this->providers->forCrypto($attempt->getProviderName());

        if (!$provider instanceof RefundableCryptoProviderInterface) {
            throw new \DomainException(
                "Provider \"{$attempt->getProviderName()}\" does not support programmatic refunds."
            );
        }

        $id     = $this->refunds->nextId();
        $refund = $provider->initiateRefund(
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
