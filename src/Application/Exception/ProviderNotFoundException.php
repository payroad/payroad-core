<?php

namespace Payroad\Application\Exception;

final class ProviderNotFoundException extends \RuntimeException
{
    public function __construct(string $providerName)
    {
        parent::__construct("No provider registered for type \"{$providerName}\".");
    }
}
