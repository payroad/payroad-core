<?php

namespace Tests\Stub;

use Payroad\Domain\PaymentFlow\P2P\P2PAttemptData;

final class StubP2PData implements P2PAttemptData
{
    public function getTransferTarget(): string      { return '1234567890'; }
    public function getRecipientBankName(): string   { return 'Stub Bank'; }
    public function getRecipientHolderName(): string { return 'John Doe'; }
}
