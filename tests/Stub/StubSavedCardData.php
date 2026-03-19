<?php

namespace Tests\Stub;

use Payroad\Domain\PaymentFlow\Card\CardSavedPaymentMethodData;

final class StubSavedCardData implements CardSavedPaymentMethodData
{
    public function getLast4(): string          { return '4242'; }
    public function getExpiryMonth(): int       { return 12; }
    public function getExpiryYear(): int        { return 2030; }
    public function getCardholderName(): ?string { return 'John Doe'; }
    public function getCardBrand(): string      { return 'visa'; }
    public function getFundingType(): string    { return 'credit'; }
    public function getIssuingCountry(): ?string { return 'US'; }
    public function isExpired(): bool           { return false; }
}
