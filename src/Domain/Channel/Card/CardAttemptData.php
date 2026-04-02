<?php

namespace Payroad\Domain\Channel\Card;

use Payroad\Domain\Attempt\AttemptData;

/**
 * Data interface for card-based payment attempts (credit/debit card, 3DS, bank redirect).
 * Implementations live in provider packages (e.g. payroad/stripe-provider).
 *
 * All card identification fields are nullable because they are unknown at attempt creation
 * and populated by the provider after the API call via updateCardData().
 */
interface CardAttemptData extends AttemptData
{
    // ── Card identification ───────────────────────────────────────────────────

    /**
     * First 6–8 digits of the card number (BIN/IIN).
     * Identifies the issuing bank, card scheme, and funding type.
     * Used for routing decisions and fraud analysis.
     */
    public function getBin(): ?string;

    /** Last 4 digits. Used for receipt display and card identification. */
    public function getLast4(): ?string;

    /** Expiry month (1–12). */
    public function getExpiryMonth(): ?int;

    /** Expiry year (4-digit, e.g. 2027). */
    public function getExpiryYear(): ?int;

    /** Name of the cardholder as printed on the card. */
    public function getCardholderName(): ?string;

    // ── Card classification ───────────────────────────────────────────────────

    /**
     * Payment scheme reported by the provider.
     * Examples: visa, mastercard, amex, unionpay, jcb, dinersclub.
     */
    public function getCardBrand(): ?string;

    /**
     * Funding type reported by the provider.
     * One of: credit, debit, prepaid, unknown.
     */
    public function getFundingType(): ?string;

    /**
     * ISO 3166-1 alpha-2 country code of the issuing bank (e.g. "US", "RU").
     * Used for geo-compliance and routing rules.
     */
    public function getIssuingCountry(): ?string;

    // ── Frontend initialisation ───────────────────────────────────────────────

    /**
     * Opaque token the client-side SDK needs to initialise the payment UI.
     *
     * Provider mapping:
     *   Stripe    → clientSecret (used by Stripe.js / Elements)
     *   Braintree → clientToken  (used by Drop-in UI)
     *
     * Null after a direct server-to-server charge that requires no frontend step.
     */
    public function getClientToken(): ?string;

    // ── Flow state ────────────────────────────────────────────────────────────

    /**
     * Whether the attempt is currently waiting for user action (3DS, bank redirect).
     * Corresponds to the AWAITING_CONFIRMATION status in the card state machine.
     */
    public function requiresUserAction(): bool;

    /**
     * Structured 3DS authentication data, present when requiresUserAction() is true
     * and the provider requires a 3DS challenge or redirect.
     * Null for direct charges and frictionless 3DS2 flows.
     */
    public function getThreeDSData(): ?ThreeDSData;
}
