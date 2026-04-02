<?php

namespace Payroad\Domain\Channel\Card;

/**
 * Structured 3DS authentication data returned by the provider.
 * Attached to CardAttemptData when the attempt requires user authentication.
 *
 * The application layer reads this to redirect or post the user to the ACS/bank page.
 */
final readonly class ThreeDSData
{
    public function __construct(
        /** 3DS protocol version, e.g. "2.2", "2.1", "1.0.2". */
        public string  $version,

        /**
         * URL where the cardholder must be redirected (3DS1) or
         * the form must be posted (3DS2 challenge).
         */
        public string  $redirectUrl,

        /**
         * 3DS2 method URL for device fingerprinting before the challenge.
         * POST a hidden iframe here before initiating the challenge.
         */
        public ?string $methodUrl = null,

        /** 3DS Server Transaction ID, used to correlate the authentication session. */
        public ?string $transactionId = null,

        /** Base64-encoded CReq (challenge request) payload for 3DS2 challenge flow. */
        public ?string $creq = null,

        /** ACS URL where the CReq must be posted to start the 3DS2 challenge. */
        public ?string $acsUrl = null,
    ) {}

    /**
     * Whether this is a 3DS2 challenge flow requiring a CReq + ACS redirect.
     * False means frictionless (no user interaction beyond method URL) or 3DS1.
     */
    public function requiresChallenge(): bool
    {
        return $this->acsUrl !== null && $this->creq !== null;
    }

    public function isV2(): bool
    {
        return str_starts_with($this->version, '2');
    }
}
