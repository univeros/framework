<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Storage;

use Altair\Idempotency\Storage\InMemoryStore;
use Altair\Idempotency\Storage\StoredResponse;
use PHPUnit\Framework\TestCase;

final class InMemoryStoreTest extends TestCase
{
    public function testClaimOnFreshKeyReturnsNull(): void
    {
        $store = new InMemoryStore();

        self::assertNull($store->claim('key-1', 'hash', 60));
    }

    public function testClaimAfterClaimReturnsInProgressEntry(): void
    {
        $store = new InMemoryStore();
        $store->claim('key-1', 'hash', 60);

        $second = $store->claim('key-1', 'hash', 60);

        self::assertInstanceOf(StoredResponse::class, $second);
        self::assertTrue($second->inProgress);
        self::assertSame('hash', $second->requestHash);
    }

    public function testCompleteOverwritesInProgressEntry(): void
    {
        $store = new InMemoryStore();
        $store->claim('key-1', 'hash', 60);

        $store->complete(
            'key-1',
            StoredResponse::completed('hash', 201, ['Content-Type' => ['application/json']], '{"ok":true}', 0),
            60,
        );

        $fetched = $store->get('key-1');
        self::assertInstanceOf(StoredResponse::class, $fetched);
        self::assertFalse($fetched->inProgress);
        self::assertSame(201, $fetched->status);
        self::assertSame('{"ok":true}', $fetched->body);
    }

    public function testClaimAfterCompleteReturnsCachedResponse(): void
    {
        $store = new InMemoryStore();
        $store->claim('key-1', 'hash', 60);
        $store->complete(
            'key-1',
            StoredResponse::completed('hash', 201, [], 'body', 0),
            60,
        );

        $third = $store->claim('key-1', 'hash', 60);

        self::assertInstanceOf(StoredResponse::class, $third);
        self::assertFalse($third->inProgress);
        self::assertSame('body', $third->body);
    }

    public function testReleaseDropsClaim(): void
    {
        $store = new InMemoryStore();
        $store->claim('key-1', 'hash', 60);

        $store->release('key-1');

        self::assertNull($store->get('key-1'));
        self::assertNull($store->claim('key-1', 'hash', 60), 'release should free the key for a fresh claim');
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        self::assertNull((new InMemoryStore())->get('nope'));
    }

    public function testExpiredEntriesAreReaped(): void
    {
        $now = 1_700_000_000;
        $clock = static function () use (&$now): int {
            return $now;
        };
        $store = new InMemoryStore($clock);

        $store->claim('key-1', 'hash', 60);

        $now += 61;

        self::assertNull($store->get('key-1'), 'expired entry should not be returned');
        self::assertNull($store->claim('key-1', 'hash', 60), 'expired entry should let a fresh claim succeed');
    }
}
