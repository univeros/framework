<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Configuration;

use Altair\Container\Container;
use Altair\Webhooks\Configuration\WebhooksConfiguration;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Contracts\InboundDeduplicatorInterface;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Signing\EnvSecretResolver;
use Altair\Webhooks\Signing\SignerRegistry;
use Altair\Webhooks\Storage\InMemoryDeduplicator;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhooksConfiguration::class)]
final class WebhooksConfigurationTest extends TestCase
{
    public function testBindsDefaultInMemoryAdaptersAndSigners(): void
    {
        $container = new Container();
        (new WebhooksConfiguration())->apply($container);

        self::assertInstanceOf(InMemoryDeduplicator::class, $container->make(InboundDeduplicatorInterface::class));
        self::assertInstanceOf(InMemoryDeliveryStore::class, $container->make(DeliveryStoreInterface::class));
        self::assertInstanceOf(EnvSecretResolver::class, $container->make(SecretResolverInterface::class));
    }

    public function testSignerRegistryShipsHmacSchemes(): void
    {
        $container = new Container();
        (new WebhooksConfiguration())->apply($container);

        $registry = $container->make(SignerRegistry::class);
        self::assertInstanceOf(SignerRegistry::class, $registry);
        self::assertTrue($registry->has('hmac-sha256'));
        self::assertTrue($registry->has('hmac-sha512'));
        self::assertSame('hmac-sha256', $registry->get('hmac-sha256')->name());
    }

    public function testSignerRegistryIsShared(): void
    {
        $container = new Container();
        (new WebhooksConfiguration())->apply($container);

        // get() returns the shared instance; make() always builds fresh.
        self::assertSame(
            $container->get(SignerRegistry::class),
            $container->get(SignerRegistry::class),
            'the signer registry should resolve as a singleton',
        );
    }
}
