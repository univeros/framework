<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Storage;

use Altair\Idempotency\Exception\IdempotencyException;
use Altair\Idempotency\Storage\ApcuStore;
use Altair\Idempotency\Storage\StoredResponse;
use PHPUnit\Framework\TestCase;

final class ApcuStoreTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('apcu_add') || !\ini_get('apc.enable_cli')) {
            self::markTestSkipped('ext-apcu (CLI-enabled) is not loaded; skipping ApcuStore tests.');
        }

        \apcu_clear_cache();
    }

    protected function tearDown(): void
    {
        if (\function_exists('apcu_clear_cache')) {
            \apcu_clear_cache();
        }
    }

    public function testConstructorRejectsMissingExtension(): void
    {
        if (\function_exists('apcu_add')) {
            self::markTestSkipped('Cannot prove constructor failure when extension IS present.');
        }

        $this->expectException(IdempotencyException::class);
        new ApcuStore();
    }

    public function testClaimFreshKey(): void
    {
        $store = new ApcuStore(keyPrefix: 'test.idem.');

        self::assertNull($store->claim('k1', 'hash', 60));
    }

    public function testConcurrentClaimReturnsExistingEntry(): void
    {
        $store = new ApcuStore(keyPrefix: 'test.idem.');
        $store->claim('k1', 'hash', 60);

        $second = $store->claim('k1', 'hash', 60);

        self::assertInstanceOf(StoredResponse::class, $second);
        self::assertTrue($second->inProgress);
    }

    public function testCompleteThenGet(): void
    {
        $store = new ApcuStore(keyPrefix: 'test.idem.');
        $store->claim('k1', 'hash', 60);

        $store->complete('k1', StoredResponse::completed('hash', 200, [], 'body', 0), 60);

        $fetched = $store->get('k1');
        self::assertInstanceOf(StoredResponse::class, $fetched);
        self::assertFalse($fetched->inProgress);
        self::assertSame('body', $fetched->body);
    }

    public function testReleaseDropsClaim(): void
    {
        $store = new ApcuStore(keyPrefix: 'test.idem.');
        $store->claim('k1', 'hash', 60);

        $store->release('k1');

        self::assertNull($store->get('k1'));
    }

    public function testKeyPrefixIsolatesNamespaces(): void
    {
        $alpha = new ApcuStore(keyPrefix: 'a.idem.');
        $beta = new ApcuStore(keyPrefix: 'b.idem.');

        $alpha->claim('same-key', 'hash-a', 60);

        self::assertNull($beta->claim('same-key', 'hash-b', 60), 'different prefixes must not collide');
    }
}
