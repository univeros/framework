<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Storage;

use Altair\Webhooks\Storage\InMemoryDeduplicator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryDeduplicator::class)]
final class InMemoryDeduplicatorTest extends TestCase
{
    public function testClaimSucceedsOnceThenFails(): void
    {
        $dedupe = new InMemoryDeduplicator();

        self::assertTrue($dedupe->claim('evt_1', 60));
        self::assertFalse($dedupe->claim('evt_1', 60));
    }

    public function testDistinctEventIdsClaimIndependently(): void
    {
        $dedupe = new InMemoryDeduplicator();

        self::assertTrue($dedupe->claim('evt_1', 60));
        self::assertTrue($dedupe->claim('evt_2', 60));
    }

    public function testReleaseAllowsReclaim(): void
    {
        $dedupe = new InMemoryDeduplicator();
        $dedupe->claim('evt_1', 60);

        $dedupe->release('evt_1');

        self::assertTrue($dedupe->claim('evt_1', 60));
    }

    public function testExpiredClaimCanBeReclaimed(): void
    {
        $dedupe = new InMemoryDeduplicator();

        // TTL of 0 expires immediately (expiresAt <= now on the next purge).
        self::assertTrue($dedupe->claim('evt_1', 0));
        self::assertTrue($dedupe->claim('evt_1', 60));
    }
}
