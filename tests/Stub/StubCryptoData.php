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
    public function getConfirmationCount(): int       { return $this->confirmationCount; }
    public function getRequiredConfirmations(): int   { return $this->requiredConfirmations; }
}
