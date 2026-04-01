<?php

namespace Payroad\Application\UseCase\Webhook;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\Exception\RefundNotFoundException;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;

final class HandleRefundWebhookUseCase
{
    public function __construct(
        private PaymentRepositoryInterface     $payments,
        private RefundRepositoryInterface      $refunds,
        private DomainEventDispatcherInterface $dispatcher
    ) {}

    public function execute(HandleRefundWebhookCommand $command): void
    {
        $result = $command->result;

        $refund = $this->refunds->findByProviderReference($command->providerName, $result->providerReference)
            ?? throw new RefundNotFoundException($result->providerReference);

        // Skip if already terminal — handles duplicate webhook delivery (at-least-once providers).
        $transitionApplied = false;
        if ($result->statusChanged && !$refund->getStatus()->isTerminal()) {
            match ($result->newStatus) {
                \Payroad\Domain\Refund\RefundStatus::PROCESSING => $refund->markProcessing($result->providerStatus),
                \Payroad\Domain\Refund\RefundStatus::SUCCEEDED  => $refund->markSucceeded($result->providerStatus),
                \Payroad\Domain\Refund\RefundStatus::FAILED     => $refund->markFailed($result->providerStatus, $result->reason),
                default => throw new \LogicException("Unexpected refund status in webhook: {$result->newStatus->value}"),
            };
            $transitionApplied = true;
        }

        // Update flow-specific data (e.g. return tx hash, acquirer reference) even without a status change.
        if ($result->updatedSpecificData !== null) {
            $refund->updateSpecificData($result->updatedSpecificData);
        }

        $this->refunds->save($refund);
        $this->dispatcher->dispatch(...$refund->releaseEvents());

        // When the refund succeeds, record it on the payment aggregate.
        // Only when the transition was actually applied — prevents double-counting on replayed webhooks.
        if ($transitionApplied && $result->newStatus->isSuccess()) {
            $payment = $this->payments->findById($refund->getPaymentId())
                ?? throw new PaymentNotFoundException($refund->getPaymentId());

            $payment->addRefund($refund->getId(), $refund->getAmount());
            $this->payments->save($payment);
            $this->dispatcher->dispatch(...$payment->releaseEvents());
        }
    }
}
