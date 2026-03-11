<?php

namespace Payroad\Application\UseCase\HandleWebhook;

final readonly class HandleWebhookCommand
{
    public function __construct(
        public string $providerType,
        public array  $payload,
        public array  $headers = []
    ) {}
}
