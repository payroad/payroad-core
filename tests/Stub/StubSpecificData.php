<?php

namespace Tests\Stub;

use Payroad\Domain\Flow\PaymentSpecificData;
use Payroad\Domain\Payment\PaymentMethodType;

final class StubSpecificData implements PaymentSpecificData
{
    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CARD;
    }

    public function getProviderType(): string
    {
        return 'stub';
    }

    public function getVersion(): int
    {
        return 1;
    }
}
