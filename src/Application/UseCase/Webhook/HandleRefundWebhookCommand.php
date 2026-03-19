<?php

namespace Payroad\Application\UseCase\Webhook;

final readonly class HandleRefundWebhookCommand
{
    public function __construct(
        public string $providerName,
        public array  $payload,
        public array  $headers = [],
    ) {}
}
