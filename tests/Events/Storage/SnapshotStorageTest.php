<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Storage;

use Altair\Events\Storage\SnapshotStorage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotStorage::class)]
class SnapshotStorageTest extends TestCase
{
    private string $tmpDir;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/altair-snapshots-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $storage = new SnapshotStorage($this->tmpDir);
        $payload = ['files' => array_fill(0, 100, 'src/some/path.php')];

        $ref = $storage->write('01HZZZ0000000000000000000A', $payload);

        $this->assertSame('snapshots/01HZZZ0000000000000000000A.json', $ref);
        $this->assertSame($payload, $storage->read('01HZZZ0000000000000000000A'));
    }

    public function testReadReturnsNullForMissingId(): void
    {
        $storage = new SnapshotStorage($this->tmpDir);
        $this->assertNull($storage->read('does-not-exist'));
    }

    public function testWriteIsAtomicAcrossRetries(): void
    {
        $storage = new SnapshotStorage($this->tmpDir);
        $storage->write('id', ['v' => 1]);
        $storage->write('id', ['v' => 2]);
        $this->assertSame(['v' => 2], $storage->read('id'));
    }

    public function testDeleteRemovesFileWhenPresent(): void
    {
        $storage = new SnapshotStorage($this->tmpDir);
        $storage->write('id', ['v' => 1]);
        $this->assertTrue($storage->delete('id'));
        $this->assertFalse($storage->delete('id'));
    }
}
