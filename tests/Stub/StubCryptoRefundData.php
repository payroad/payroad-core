<?php

namespace Tests\Stub;

use Payroad\Port\Provider\Crypto\CryptoRefundData;

final class StubCryptoRefundData implements CryptoRefundData
{
    public function getReturnTxHash(): ?string  { return '0xdeadbeef'; }
    public function getReturnAddress(): ?string { return '0xABC123'; }
}
