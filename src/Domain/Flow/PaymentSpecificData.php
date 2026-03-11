<?php

namespace Payroad\Domain\Flow;

use Payroad\Domain\Payment\PaymentMethodType;

/**
 * Marker interface for provider-specific attempt data.
 * Concrete implementations live in provider integration packages (e.g. payment-stripe).
 */
interface PaymentSpecificData
{
    public function getMethodType(): PaymentMethodType;

    public function getProviderType(): string;

    /** Schema version — bump when the shape of the data changes. */
    public function getVersion(): int;
}
