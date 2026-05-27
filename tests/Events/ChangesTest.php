<?php

declare(strict_types=1);

namespace Altair\Tests\Events;

use Altair\Events\Changes;
use Altair\Events\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Changes::class)]
class ChangesTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $changes = new Changes();
        $this->assertTrue($changes->isEmpty());
        $this->assertSame([], $changes->toArray());
    }

    public function testWithBucketAppendsImmutably(): void
    {
        $a = new Changes();
        $b = $a->withBucket('created', 'a.php', 'b.php');
        $c = $b->withBucket('created', 'c.php');

        $this->assertTrue($a->isEmpty(), 'Original Changes must remain empty (immutable).');
        $this->assertSame(['created' => ['a.php', 'b.php']], $b->buckets);
        $this->assertSame(['created' => ['a.php', 'b.php', 'c.php']], $c->buckets);
    }

    public function testWithSnapshotRefAttachesReference(): void
    {
        $changes = (new Changes(['modified' => ['x']]))->withSnapshotRef('snapshots/01H.json');

        $this->assertFalse($changes->isEmpty());
        $this->assertSame('snapshots/01H.json', $changes->snapshotRef);
        $this->assertSame('snapshots/01H.json', $changes->toArray()['snapshot_ref']);
    }

    public function testRejectsNonStringBucketValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Changes(['created' => [123]]); // @phpstan-ignore-line — testing the runtime guard
    }

    public function testRejectsEmptyBucketKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Changes(['' => ['a.php']]);
    }

    public function testFromArrayHydratesBucketsAndSnapshotRef(): void
    {
        $changes = Changes::fromArray([
            'created' => ['a.php', 'b.php'],
            'snapshot_ref' => 'snapshots/01H.json',
        ]);

        $this->assertSame(['created' => ['a.php', 'b.php']], $changes->buckets);
        $this->assertSame('snapshots/01H.json', $changes->snapshotRef);
    }

    public function testFromArrayRejectsScalarBucket(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Changes::fromArray(['created' => 'not-a-list']);
    }
}
