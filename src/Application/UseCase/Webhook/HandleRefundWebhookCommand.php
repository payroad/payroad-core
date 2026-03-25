<?php

namespace Payroad\Application\UseCase\Webhook;

use Payroad\Port\Provider\RefundWebhookResult;

final readonly class HandleRefundWebhookCommand
{
    public function __construct(
        public string             $providerName,
        public RefundWebhookResult $result,
    ) {}
}
