<?php

namespace Payroad\Application\UseCase\Webhook;

use Payroad\Port\Provider\WebhookResult;

final readonly class HandleWebhookCommand
{
    public function __construct(
        public string        $providerName,
        public WebhookResult $result,
    ) {}
}
