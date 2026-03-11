<?php

namespace Payroad\Port;

interface ProviderRegistryInterface
{
    /** @throws \Payroad\Application\Exception\ProviderNotFoundException */
    public function getByType(string $providerType): PaymentProviderInterface;
}
