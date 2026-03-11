<?php

namespace Payroad\Application\UseCase\InitiateAttempt;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Port\DomainEventDispatcherInterface;
use Payroad\Port\PaymentAttemptRepositoryInterface;
use Payroad\Port\PaymentRepositoryInterface;
use Payroad\Port\ProviderRegistryInterface;

final class InitiateAttemptUseCase
{
    public function __construct(
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher
    ) {}

    public function execute(InitiateAttemptCommand $command): PaymentAttempt
    {
        $payment = $this->payments->findById($command->paymentId)
            ?? throw new PaymentNotFoundException($command->paymentId);

        if ($payment->isExpired()) {
            $payment->expire();
            $this->payments->save($payment);
            $this->dispatcher->dispatch(...$payment->releaseEvents());
            throw new \DomainException("Payment \"{$command->paymentId->value}\" has expired.");
        }

        if ($payment->getStatus()->isTerminal()) {
            throw new \DomainException(
                "Cannot initiate attempt on a terminal payment (status: {$payment->getStatus()->value})."
            );
        }

        $provider    = $this->providers->getByType($command->providerType);
        $specificData = $provider->buildInitialSpecificData();

        $attempt = PaymentAttempt::create(
            $payment->getId(),
            $command->methodType,
            $command->providerType,
            $specificData
        );

        $provider->initiate($attempt, $payment->getAmount());

        $payment->markProcessing();

        $this->attempts->save($attempt);
        $this->payments->save($payment);

        $this->dispatcher->dispatch(
            ...$attempt->releaseEvents(),
            ...$payment->releaseEvents()
        );

        return $attempt;
    }
}
