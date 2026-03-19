<?php

namespace Payroad\Application\Exception;

final class RefundNotFoundException extends \DomainException
{
    public function __construct(string $providerReference)
    {
        parent::__construct("Refund with provider reference \"{$providerReference}\" not found.");
    }
}
