<?php

declare(strict_types=1);

namespace Payroad\Port\Provider;

/**
 * Implemented by each provider package to describe how to instantiate the provider.
 *
 * Lives in payroad-core so the Symfony bridge (and any other framework adapter)
 * can depend on it without coupling to provider packages.
 *
 * Implementations must have a no-argument constructor.
 */
interface ProviderFactoryInterface
{
    /**
     * Instantiate the provider from a resolved config array.
     */
    public function create(array $config): PaymentProviderInterface;
}
