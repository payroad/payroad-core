# zykovnick/payment-core

[![GitHub](https://img.shields.io/badge/github-zykovnick%2Fpayment--core-blue?logo=github)](https://github.com/zykovnick/payment-core)

Framework-independent domain core for the Payroad payment gateway.
Built on Domain-Driven Design and Hexagonal Architecture.

---

## What this package is

`payment-core` is the **shared kernel** of the Payroad ecosystem. It contains:

- Domain aggregates with no framework or database dependencies
- Port interfaces that integration packages and the gateway implement
- Application use cases that orchestrate the domain

It knows **nothing** about Stripe, databases, HTTP, or any specific provider.

---

## Package ecosystem

```
zykovnick/payment-core       ← this package
paylet/payment-stripe        → implements PaymentProviderInterface for Stripe
paylet/payment-nowpayments   → implements PaymentProviderInterface for crypto
paylet/payment-p2p           → implements PaymentProviderInterface for P2P
paylet-gateway               → PHP application, wires everything together
```

---

## Architecture

### Two aggregates

**`Payment`** — thin business document. Represents the merchant's intent to collect money.
Holds: amount, merchant, customer, idempotency key, metadata, expiry, derived status.
Does **not** contain attempts.

**`PaymentAttempt`** — main operational aggregate. Represents one execution attempt via a specific provider.
Holds: method type, provider type, provider reference, universal status, raw provider status, specific data.

```
Payment ←──────────── PaymentAttempt
  id                    id
  amount                paymentId  (FK only, not a reference)
  status                status     (AttemptStatus — 7 universal states)
  successfulAttemptId   providerStatus  (raw: "requires_capture", "confirming"…)
                        specificData    (interface — impl lives in provider package)
```

### Dual status on PaymentAttempt

```
AttemptStatus    business-level state — 7 universal values
providerStatus   raw provider state   — "requires_capture", "waiting", "confirming"…
```

State machines operate on `providerStatus` inside integration packages and map to `AttemptStatus`.
`Payment` reacts only to `AttemptStatus` via domain events.

### State machines (by method type, not provider)

Four state machines live in this package, one per payment method:

```
CardStateMachine
  PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
          └──► PROCESSING            └──► FAILED
          └──► FAILED                └──► CANCELED

CryptoStateMachine
  PENDING ──► PROCESSING ──► SUCCEEDED
                         └──► FAILED
                         └──► EXPIRED

P2PStateMachine
  PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
          └──► FAILED               └──► FAILED
                                    └──► EXPIRED

CashStateMachine
  PENDING ──► AWAITING_CONFIRMATION ──► SUCCEEDED
          └──► FAILED               └──► EXPIRED
```

Provider-specific sub-flows (e.g. Stripe's `requires_capture`) are handled inside the integration package before mapping to these universal states.

---

## Directory structure

```
src/
├── Domain/
│   ├── Money/
│   │   ├── Currency.php            ISO 4217, format-validated value object
│   │   └── Money.php               Integer minor units, bcmath arithmetic
│   ├── Payment/
│   │   ├── Payment.php             Thin aggregate
│   │   ├── PaymentId.php
│   │   ├── PaymentStatus.php       PENDING|PROCESSING|SUCCEEDED|FAILED|CANCELED|EXPIRED
│   │   ├── IdempotencyKey.php
│   │   ├── MerchantId.php
│   │   ├── CustomerId.php
│   │   ├── PaymentMetadata.php     Immutable key-value merchant metadata
│   │   └── PaymentMethodType.php   CARD|CRYPTO|P2P|CASH
│   ├── Attempt/
│   │   ├── PaymentAttempt.php      Operational aggregate
│   │   ├── AttemptId.php
│   │   ├── AttemptStatus.php
│   │   └── StateMachine/
│   │       ├── AttemptStateMachineInterface.php
│   │       ├── CardStateMachine.php
│   │       ├── CryptoStateMachine.php
│   │       ├── P2PStateMachine.php
│   │       └── CashStateMachine.php
│   ├── Flow/
│   │   └── PaymentSpecificData.php  Interface only — impls live in provider packages
│   ├── Event/
│   │   ├── DomainEvent.php
│   │   ├── Payment/                 PaymentCreated|Succeeded|Failed|Canceled|Expired
│   │   └── Attempt/                 AttemptInitiated|StatusChanged|Succeeded|Failed
│   └── Exception/
│       └── InvalidTransitionException.php
├── Port/
│   ├── PaymentRepositoryInterface.php
│   ├── PaymentAttemptRepositoryInterface.php  (includes findByProviderReference)
│   ├── PaymentProviderInterface.php
│   ├── ProviderRegistryInterface.php
│   ├── StateMachineRegistryInterface.php
│   ├── DomainEventDispatcherInterface.php
│   └── WebhookResult.php            DTO returned by parseWebhook()
└── Application/
    ├── UseCase/
    │   ├── CreatePayment/           Idempotent payment creation
    │   ├── InitiateAttempt/         Expiry check + provider initiation
    │   └── HandleWebhook/           Parse → transition → propagate to Payment
    └── Exception/
        ├── PaymentNotFoundException.php
        ├── AttemptNotFoundException.php
        ├── DuplicatePaymentException.php
        └── ProviderNotFoundException.php
```

---

## Key design decisions

**`Money` uses integer minor units + bcmath**
`(int)(1.15 * 100) === 114` in PHP. All monetary values are stored as integers (cents, pence) and constructed from decimal strings via `bcmath` to avoid float errors.

**`PaymentSpecificData` is an interface, not a class**
`CardSpecificData`, `CryptoSpecificData`, etc. live in their respective integration packages. Adding a new provider requires no changes to this package.

**`PaymentAttempt::transitionTo()` is the single status-change point**
Status can only change via `transitionTo()`, which is called exclusively by `AttemptStateMachineInterface` implementations. Direct status mutation from outside is not possible.

**`WebhookResult` decouples providers from aggregates**
`PaymentProviderInterface::parseWebhook()` returns a `WebhookResult` DTO. The provider never touches the aggregate directly — `HandleWebhookUseCase` applies the result.

**`version` field on both aggregates**
Optimistic locking support. Implementations of `PaymentRepositoryInterface` and `PaymentAttemptRepositoryInterface` are expected to enforce version checks on save.

**Idempotency at domain level**
`Payment` is keyed by `IdempotencyKey`. `CreatePaymentUseCase` checks for an existing payment before creating a new one. The database layer must additionally enforce a `UNIQUE` constraint.

---

## Usage example

### Creating a payment

```php
use Payroad\Application\UseCase\CreatePayment\CreatePaymentCommand;
use Payroad\Application\UseCase\CreatePayment\CreatePaymentUseCase;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\PaymentMetadata;

$command = new CreatePaymentCommand(
    amount:         Money::ofDecimal('99.99', Currency::of('USD')),
    merchantId:     MerchantId::of('merchant-123'),
    customerId:     CustomerId::of('customer-456'),
    idempotencyKey: IdempotencyKey::of('order-789'),
    metadata:       PaymentMetadata::fromArray(['orderId' => '789']),
    expiresAt:      new DateTimeImmutable('+30 minutes'),
);

$payment = $useCase->execute($command);
```

### Initiating an attempt

```php
use Payroad\Application\UseCase\InitiateAttempt\InitiateAttemptCommand;
use Payroad\Domain\Payment\PaymentMethodType;

$command = new InitiateAttemptCommand(
    paymentId:    $payment->getId(),
    methodType:   PaymentMethodType::CARD,
    providerType: 'stripe',
);

$attempt = $useCase->execute($command);
```

### Handling an incoming webhook

```php
use Payroad\Application\UseCase\HandleWebhook\HandleWebhookCommand;

$command = new HandleWebhookCommand(
    providerType: 'stripe',
    payload:      $request->toArray(),
    headers:      $request->headers->all(),
);

$useCase->execute($command);
```

---

## Implementing a provider

Create a new Composer package (e.g. `paylet/payment-stripe`) and implement:

```php
// 1. Specific data for this provider
final readonly class StripeSpecificData implements PaymentSpecificData { ... }

// 2. Provider — handles API calls and webhook parsing
final class StripePaymentProvider implements PaymentProviderInterface
{
    public function supports(string $providerType): bool
    {
        return $providerType === 'stripe';
    }

    public function buildInitialSpecificData(): PaymentSpecificData
    {
        return new StripeSpecificData();
    }

    public function initiate(PaymentAttempt $attempt, Money $amount): void
    {
        // Call Stripe API, then:
        $attempt->setProviderReference($intent->id);
        $attempt->updateSpecificData(new StripeSpecificData(
            paymentIntentId: $intent->id,
            clientSecret:    $intent->client_secret,
        ));
    }

    public function parseWebhook(array $payload, array $headers): WebhookResult
    {
        // Validate Stripe-Signature header, parse event, map to AttemptStatus
        return new WebhookResult(
            providerReference: $payload['data']['object']['id'],
            newStatus:         AttemptStatus::SUCCEEDED,
            providerStatus:    'succeeded',
            statusChanged:     true,
        );
    }
}
```

The gateway registers the provider in `ProviderRegistryInterface` and the matching state machine in `StateMachineRegistryInterface`. No changes to this package are needed.

---

## Requirements

- PHP 8.2+
- `ramsey/uuid` ^4.0

## Repository

[https://github.com/zykovnick/payment-core](https://github.com/zykovnick/payment-core)

## License

MIT
