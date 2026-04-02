<?php

namespace Tests\Domain\SavedPaymentMethod;

use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Channel\Card\CardSavedPaymentMethod;
use Payroad\Domain\SavedPaymentMethod\Event\PaymentMethodRemoved;
use Payroad\Domain\SavedPaymentMethod\Event\PaymentMethodSaved;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodStatus;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSavedCardData;

final class SavedPaymentMethodTest extends TestCase
{
    private function makeMethod(): CardSavedPaymentMethod
    {
        return CardSavedPaymentMethod::create(
            SavedPaymentMethodId::generate(),
            CustomerId::of('customer-1'),
            'stub',
            'tok_abc123',
            new StubSavedCardData(),
        );
    }

    public function testCreateSetsActiveStatus(): void
    {
        $method = $this->makeMethod();
        $this->assertSame(SavedPaymentMethodStatus::ACTIVE, $method->getStatus());
    }

    public function testCreateRecordsPaymentMethodSavedEvent(): void
    {
        $method = $this->makeMethod();
        $events = $method->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentMethodSaved::class, $events[0]);
    }

    public function testPaymentMethodSavedEventHasCorrectFields(): void
    {
        $id         = SavedPaymentMethodId::generate();
        $customerId = CustomerId::of('customer-1');
        $method     = CardSavedPaymentMethod::create($id, $customerId, 'stripe', 'tok_xyz', new StubSavedCardData());

        /** @var PaymentMethodSaved $event */
        $event = $method->releaseEvents()[0];

        $this->assertTrue($id->equals($event->savedMethodId));
        $this->assertTrue($customerId->equals($event->customerId));
        $this->assertSame(PaymentMethodType::CARD, $event->methodType);
        $this->assertSame('stripe', $event->providerName);
    }

    public function testGetMethodTypeReturnsCard(): void
    {
        $this->assertSame(PaymentMethodType::CARD, $this->makeMethod()->getMethodType());
    }

    public function testGetProviderTokenReturnsToken(): void
    {
        $this->assertSame('tok_abc123', $this->makeMethod()->getProviderToken());
    }

    public function testIsUsableReturnsTrueWhenActive(): void
    {
        $this->assertTrue($this->makeMethod()->getStatus()->isUsable());
    }

    public function testRemoveTransitionsToRemovedStatus(): void
    {
        $method = $this->makeMethod();
        $method->releaseEvents();

        $method->remove();

        $this->assertSame(SavedPaymentMethodStatus::REMOVED, $method->getStatus());
    }

    public function testRemoveRecordsPaymentMethodRemovedEvent(): void
    {
        $method = $this->makeMethod();
        $method->releaseEvents();

        $method->remove();
        $events = $method->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentMethodRemoved::class, $events[0]);
    }

    public function testRemoveIsIdempotent(): void
    {
        $method = $this->makeMethod();
        $method->releaseEvents();

        $method->remove();
        $method->releaseEvents();
        $method->remove();

        $this->assertCount(0, $method->releaseEvents());
        $this->assertSame(SavedPaymentMethodStatus::REMOVED, $method->getStatus());
    }

    public function testRemovedMethodIsNotUsable(): void
    {
        $method = $this->makeMethod();
        $method->remove();

        $this->assertFalse($method->getStatus()->isUsable());
    }

    public function testExpireTransitionsToExpiredStatus(): void
    {
        $method = $this->makeMethod();
        $method->expire();

        $this->assertSame(SavedPaymentMethodStatus::EXPIRED, $method->getStatus());
    }

    public function testExpiredMethodIsNotUsable(): void
    {
        $method = $this->makeMethod();
        $method->expire();

        $this->assertFalse($method->getStatus()->isUsable());
    }
}
