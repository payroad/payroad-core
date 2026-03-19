<?php

namespace Tests\Application\UseCase\Webhook;

use Payroad\Application\Exception\RefundNotFoundException;
use Payroad\Application\UseCase\Webhook\HandleRefundWebhookCommand;
use Payroad\Application\UseCase\Webhook\HandleRefundWebhookUseCase;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Domain\PaymentFlow\Card\CardRefund;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStatus;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\PaymentProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Provider\RefundWebhookResult;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubCardRefundData;

final class HandleRefundWebhookUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject  $payments;
    private RefundRepositoryInterface&MockObject   $refunds;
    private ProviderRegistryInterface&MockObject   $providers;
    private DomainEventDispatcherInterface&MockObject $dispatcher;
    private PaymentProviderInterface&MockObject    $provider;
    private HandleRefundWebhookUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments    = $this->createMock(PaymentRepositoryInterface::class);
        $this->refunds     = $this->createMock(RefundRepositoryInterface::class);
        $this->providers   = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher  = $this->createMock(DomainEventDispatcherInterface::class);
        $this->provider    = $this->createMock(PaymentProviderInterface::class);

        $this->providers->method('getByName')->willReturn($this->provider);

        $this->useCase = new HandleRefundWebhookUseCase(
            $this->payments,
            $this->refunds,
            $this->providers,
            $this->dispatcher
        );
    }

    private function makeRefund(Payment $payment): CardRefund
    {
        $refund = CardRefund::create(
            RefundId::generate(),
            $payment->getId(),
            $payment->getSuccessfulAttemptId(),
            'stub',
            Money::ofMinor(500, new Currency('USD', 2)),
            new StubCardRefundData()
        );
        $refund->setProviderReference('re_abc123');
        $refund->releaseEvents();
        return $refund;
    }

    private function makeSucceededPayment(): Payment
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );
        $payment->markProcessing();
        $payment->markSucceeded(PaymentAttemptId::generate());
        $payment->releaseEvents();
        return $payment;
    }

    public function testExecuteThrowsWhenRefundNotFound(): void
    {
        $this->provider->method('parseRefundWebhook')->willReturn(
            new RefundWebhookResult('re_unknown', RefundStatus::SUCCEEDED, 'succeeded')
        );
        $this->refunds->method('findByProviderReference')->willReturn(null);

        $this->expectException(RefundNotFoundException::class);
        $this->useCase->execute(new HandleRefundWebhookCommand('stub', []));
    }

    public function testExecuteAppliesSucceededTransitionAndUpdatesPayment(): void
    {
        $payment = $this->makeSucceededPayment();
        $refund  = $this->makeRefund($payment);

        $this->provider->method('parseRefundWebhook')->willReturn(
            new RefundWebhookResult('re_abc123', RefundStatus::SUCCEEDED, 'succeeded')
        );
        $this->refunds->method('findByProviderReference')->willReturn($refund);
        $this->payments->method('findById')->willReturn($payment);

        $this->payments->expects($this->once())->method('save')->with($payment);

        $this->useCase->execute(new HandleRefundWebhookCommand('stub', []));

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->getStatus());
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->getStatus());
    }

    public function testExecuteFullRefundSetsPaymentToRefunded(): void
    {
        $payment = $this->makeSucceededPayment();
        $refund  = CardRefund::create(
            RefundId::generate(),
            $payment->getId(),
            $payment->getSuccessfulAttemptId(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)), // full amount
            new StubCardRefundData()
        );
        $refund->setProviderReference('re_full');
        $refund->releaseEvents();

        $this->provider->method('parseRefundWebhook')->willReturn(
            new RefundWebhookResult('re_full', RefundStatus::SUCCEEDED, 'succeeded')
        );
        $this->refunds->method('findByProviderReference')->willReturn($refund);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute(new HandleRefundWebhookCommand('stub', []));

        $this->assertSame(PaymentStatus::REFUNDED, $payment->getStatus());
    }

    public function testExecuteSkipsPaymentUpdateOnFailedRefund(): void
    {
        $payment = $this->makeSucceededPayment();
        $refund  = $this->makeRefund($payment);

        $this->provider->method('parseRefundWebhook')->willReturn(
            new RefundWebhookResult('re_abc123', RefundStatus::FAILED, 'failed', 'provider_error')
        );
        $this->refunds->method('findByProviderReference')->willReturn($refund);

        $this->payments->expects($this->never())->method('save');

        $this->useCase->execute(new HandleRefundWebhookCommand('stub', []));

        $this->assertSame(RefundStatus::FAILED, $refund->getStatus());
        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testExecuteDoesNotPropagateToPaymentWhenRefundAlreadyTerminal(): void
    {
        $payment = $this->makeSucceededPayment();
        $refund  = $this->makeRefund($payment);
        $refund->applyTransition(RefundStatus::SUCCEEDED, 'succeeded');
        $refund->releaseEvents();

        // Duplicate webhook: refund already SUCCEEDED — transition must be skipped.
        $this->provider->method('parseRefundWebhook')->willReturn(
            new RefundWebhookResult('re_abc123', RefundStatus::SUCCEEDED, 'succeeded')
        );
        $this->refunds->method('findByProviderReference')->willReturn($refund);

        // Payment must not be loaded or modified — would double-count the refund.
        $this->payments->expects($this->never())->method('findById');
        $this->payments->expects($this->never())->method('save');

        $this->useCase->execute(new HandleRefundWebhookCommand('stub', []));

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->getStatus());
    }

    public function testExecuteSkipsTransitionOnAlreadyTerminalRefund(): void
    {
        $payment = $this->makeSucceededPayment();
        $refund  = $this->makeRefund($payment);
        $refund->applyTransition(RefundStatus::SUCCEEDED, 'succeeded');
        $refund->releaseEvents();

        $this->provider->method('parseRefundWebhook')->willReturn(
            new RefundWebhookResult('re_abc123', RefundStatus::FAILED, 'failed') // duplicate
        );
        $this->refunds->method('findByProviderReference')->willReturn($refund);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute(new HandleRefundWebhookCommand('stub', []));

        // Status must remain SUCCEEDED, not change to FAILED
        $this->assertSame(RefundStatus::SUCCEEDED, $refund->getStatus());
    }
}
