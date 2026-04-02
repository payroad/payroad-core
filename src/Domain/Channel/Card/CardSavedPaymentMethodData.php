<?php

namespace Payroad\Domain\Channel\Card;

use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodData;

/**
 * Card details stored alongside a tokenized payment method.
 * Implementations live in provider packages.
 */
interface CardSavedPaymentMethodData extends SavedPaymentMethodData
{
    public function getLast4(): string;
    public function getExpiryMonth(): int;
    public function getExpiryYear(): int;
    public function getCardholderName(): ?string;
    public function getCardBrand(): string;
    public function getFundingType(): string;
    public function getIssuingCountry(): ?string;

    /** Whether this saved method has passed its expiry date. */
    public function isExpired(): bool;
}
