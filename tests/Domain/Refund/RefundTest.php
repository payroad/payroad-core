<?php

namespace Tests\Domain\Refund;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\PaymentFlow\Card\CardRefund;
use Payroad\Domain\Refund\Event\RefundFailed;
use Payroad\Domain\Refund\Event\RefundInitiated;
use Payroad\Domain\Refund\Event\RefundSucceeded;
use Payroad\Domain\Refund\Exception\InvalidRefundTransitionException;
use Payroad\Domain\Refund\RefundStatus;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubCardRefundData;

final class RefundTest extends TestCase
{
    private function makeRefund(?Money $amount = null): CardRefund
    {
        return CardRefund::create(
            \Payroad\Domain\Refund\RefundId::generate(),
            PaymentId::generate(),
            PaymentAttemptId::generate(),
            'stub',
            $amount ?? Money::ofMinor(500, new Currency('USD', 2)),
            new StubCardRefundData()
        );
    }

    public function testCreateRecordsRefundInitiatedEvent(): void
    {
        $refund = $this->makeRefund();
        $events = $refund->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(RefundInitiated::class, $events[0]);
    }

    public function testInitialStatusIsPending(): void
    {
        $refund = $this->makeRefund();
        $this->assertSame(RefundStatus::PENDING, $refund->getStatus());
    }

    public function testTransitionPendingToProcessing(): void
    {
        $refund = $this->makeRefund();
        $refund->releaseEvents();

        $refund->applyTransition(RefundStatus::PROCESSING, 'processing');

        $this->assertSame(RefundStatus::PROCESSING, $refund->getStatus());
    }

    public function testTransitionToSucceededRecordsRefundSucceededEvent(): void
    {
        $refund = $this->makeRefund();
        $refund->releaseEvents();

        $refund->applyTransition(RefundStatus::PROCESSING, 'processing');
        $refund->releaseEvents();
        $refund->applyTransition(RefundStatus::SUCCEEDED, 'succeeded');

        $events = $refund->releaseEvents();
        $succeeded = array_filter($events, fn($e) => $e instanceof RefundSucceeded);

        $this->assertCount(1, $succeeded);
    }

    public function testTransitionToFailedRecordsRefundFailedEvent(): void
    {
        $refund = $this->makeRefund();
        $refund->releaseEvents();

        $refund->applyTransition(RefundStatus::FAILED, 'failed', 'insufficient_funds');

        $events = $refund->releaseEvents();
        $failed = array_filter($events, fn($e) => $e instanceof RefundFailed);

        $this->assertCount(1, $failed);
    }

    public function testCannotTransitionFromTerminalStatus(): void
    {
        $refund = $this->makeRefund();
        $refund->applyTransition(RefundStatus::SUCCEEDED, 'succeeded');

        $this->expectException(InvalidRefundTransitionException::class);
        $refund->applyTransition(RefundStatus::FAILED, 'failed');
    }

    public function testCannotTransitionFromProcessingToPending(): void
    {
        $refund = $this->makeRefund();
        $refund->applyTransition(RefundStatus::PROCESSING, 'processing');

        $this->expectException(InvalidRefundTransitionException::class);
        $refund->applyTransition(RefundStatus::PENDING, 'pending');
    }

    public function testSetProviderReference(): void
    {
        $refund = $this->makeRefund();
        $refund->setProviderReference('re_abc123');

        $this->assertSame('re_abc123', $refund->getProviderReference());
    }

    public function testIncrementVersion(): void
    {
        $refund = $this->makeRefund();
        $this->assertSame(0, $refund->getVersion());

        $refund->incrementVersion();

        $this->assertSame(1, $refund->getVersion());
    }

    public function testGetAmountReturnsCorrectValue(): void
    {
        $amount = Money::ofMinor(300, new Currency('USD', 2));
        $refund = $this->makeRefund($amount);

        $this->assertTrue($amount->equals($refund->getAmount()));
    }
}
