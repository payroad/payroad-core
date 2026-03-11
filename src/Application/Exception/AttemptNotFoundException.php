<?php

namespace Payroad\Application\Exception;

final class AttemptNotFoundException extends \RuntimeException
{
    public function __construct(string $providerReference)
    {
        parent::__construct("Attempt with provider reference \"{$providerReference}\" not found.");
    }
}
