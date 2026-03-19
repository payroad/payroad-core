<?php

namespace Payroad\Port\Provider\Card;

/**
 * Flow-specific context for card payment initiation.
 * Required by CardProviderInterface::initiateCardAttempt() for 3DS and fraud scoring.
 */
final readonly class CardAttemptContext
{
    public function __construct(
        public string  $customerIp,
        public string  $browserUserAgent,
        public ?string $billingAddressCountry = null,
        public bool    $requestThreeDS        = true,
    ) {}
}
