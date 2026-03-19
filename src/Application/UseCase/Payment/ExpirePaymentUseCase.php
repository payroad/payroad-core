<?php

namespace Payroad\Application\UseCase\Payment;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

/**
 * Transitions a payment to EXPIRED status.
 * Called by a scheduler or TTL-enforcement job when a payment's expiresAt has passed.
 * Idempotent: if the payment is already terminal, returns silently.
 *
 * @throws PaymentNotFoundException
 */
final class ExpirePaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface     $payments,
        private DomainEventDispatcherInterface $dispatcher,
    ) {}

    public function execute(ExpirePaymentCommand $command): void
    {
        $payment = $this->payments->findById($command->paymentId)
            ?? throw new PaymentNotFoundException($command->paymentId);

        if ($payment->getStatus()->isTerminal()) {
            return;
        }

        $payment->expire();

        $this->payments->save($payment);
        $this->dispatcher->dispatch(...$payment->releaseEvents());
    }
}
