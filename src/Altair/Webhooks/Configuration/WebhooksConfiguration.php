<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Contracts\InboundDeduplicatorInterface;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Signing\EnvSecretResolver;
use Altair\Webhooks\Signing\SignerRegistry;
use Altair\Webhooks\Storage\InMemoryDeduplicator;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use Override;

/**
 * Wires the webhook primitive into the container.
 *
 * v1 binds the in-memory adapters as the defaults — they keep a bare
 * `bin/altair` and unit tests dependency-free. Host applications that need
 * durability swap to the Redis adapters by re-binding
 * {@see InboundDeduplicatorInterface} / {@see DeliveryStoreInterface} in their
 * own Configuration after this one has applied (they construct the
 * `RedisDeduplicator` / `RedisDeliveryStore` with their own `\Redis` client).
 *
 * Secrets resolve from `WEBHOOK_SECRET_<NAME>` via {@see EnvSecretResolver};
 * the {@see SignerRegistry} ships the always-available HMAC signers plus
 * Ed25519 when ext-sodium is loaded.
 *
 * The per-endpoint verification policy (signing / dedupe_ttl / timestamp
 * window) is sourced from the generated Action's `webhook()` accessor (#188)
 * and consumed by `ActionAwareWebhookVerifyMiddleware`; the outbound
 * dispatcher additionally needs `Symfony\Component\Messenger\MessageBusInterface`
 * bound by `MessengerConfiguration`. This Configuration owns only the
 * adapter / signer / secret bindings, not the middleware lifecycle.
 */
final readonly class WebhooksConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        $container->alias(InboundDeduplicatorInterface::class, InMemoryDeduplicator::class);
        $container->alias(DeliveryStoreInterface::class, InMemoryDeliveryStore::class);
        $container->alias(SecretResolverInterface::class, EnvSecretResolver::class);
        $container->singleton(SignerRegistry::class, static fn(): SignerRegistry => SignerRegistry::default());
    }
}
