<?php

namespace Payroad\Application\UseCase\Payment;

use Payroad\Domain\Payment\Payment;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class CreatePaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface    $payments,
        private DomainEventDispatcherInterface $dispatcher
    ) {}

    /**
     * Idempotent: if a payment with the supplied ID already exists, returns it
     * without creating a duplicate. Callers should generate a stable UUID before
     * calling this use case and reuse it on retry.
     */
    public function execute(CreatePaymentCommand $command): Payment
    {
        $id = $command->id ?? $this->payments->nextId();

        $existing = $this->payments->findById($id);
        if ($existing !== null) {
            return $existing;
        }

        $payment = Payment::create(
            $id,
            $command->amount,
            $command->customerId,
            $command->metadata,
            $command->expiresAt
        );

        $this->payments->save($payment);
        $this->dispatcher->dispatch(...$payment->releaseEvents());

        return $payment;
    }
}
