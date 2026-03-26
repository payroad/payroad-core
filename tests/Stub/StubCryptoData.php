<?php

namespace Tests\Stub;

use Payroad\Domain\PaymentFlow\Crypto\CryptoAttemptData;

final class StubCryptoData implements CryptoAttemptData
{
    public function __construct(
        private int $confirmationCount    = 0,
        private int $requiredConfirmations = 3,
    ) {}

    public function getWalletAddress(): string        { return 'stub-wallet-address'; }
    public function getPayCurrency(): string          { return 'BTC'; }
    public function getPayAmount(): string            { return '0.001'; }
    public function getConfirmationCount(): int       { return $this->confirmationCount; }
    public function getRequiredConfirmations(): int   { return $this->requiredConfirmations; }
    public function getActualPaidAmount(): ?string    { return null; }
    public function getPaymentUrl(): ?string          { return null; }
    public function getMemo(): ?string                { return null; }
    public function toArray(): array                  { return []; }
}
