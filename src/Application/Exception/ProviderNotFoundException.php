<?php

namespace Payroad\Application\Exception;

final class ProviderNotFoundException extends \RuntimeException
{
    public function __construct(string $providerType)
    {
        parent::__construct("No provider registered for type \"{$providerType}\".");
    }
}
