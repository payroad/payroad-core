<?php

namespace Tests\Stub;

use Payroad\Domain\Channel\Card\CardAttemptData;
use Payroad\Domain\Channel\Card\ThreeDSData;

final class StubSpecificDataWith3DS implements CardAttemptData
{
    private ThreeDSData $threeDSData;

    public function __construct(?ThreeDSData $threeDSData = null)
    {
        $this->threeDSData = $threeDSData ?? new ThreeDSData(
            version:       '2.2',
            redirectUrl:   'https://acs.example.com/challenge',
            methodUrl:     'https://acs.example.com/method',
            transactionId: 'tds-txn-001',
            creq:          base64_encode('{"threeDSServerTransID":"tds-txn-001"}'),
            acsUrl:        'https://acs.example.com/challenge',
        );
    }

    public function getBin(): ?string            { return '424242'; }
    public function getLast4(): ?string          { return '4242'; }
    public function getExpiryMonth(): ?int       { return 12; }
    public function getExpiryYear(): ?int        { return 2030; }
    public function getCardholderName(): ?string { return 'John Doe'; }
    public function getCardBrand(): ?string      { return 'visa'; }
    public function getFundingType(): ?string    { return 'credit'; }
    public function getIssuingCountry(): ?string { return 'US'; }
    public function getClientToken(): ?string    { return null; }
    public function requiresUserAction(): bool   { return true; }
    public function getThreeDSData(): ?ThreeDSData { return $this->threeDSData; }
    public function toArray(): array               { return []; }
}
