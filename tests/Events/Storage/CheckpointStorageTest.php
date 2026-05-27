<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Storage;

use Altair\Events\Exception\InvalidArgumentException;
use Altair\Events\Storage\CheckpointStorage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckpointStorage::class)]
class CheckpointStorageTest extends TestCase
{
    private string $tmpDir;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/altair-checkpoints-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
    }

    public function testCreateAndReadCheckpoint(): void
    {
        $storage = new CheckpointStorage($this->tmpDir);
        $storage->create('feat/posts', '01HEEE0000000000000000000Z');

        $this->assertTrue($storage->exists('feat/posts'));

        $payload = $storage->read('feat/posts');
        $this->assertSame('feat/posts', $payload['name']);
        $this->assertSame('01HEEE0000000000000000000Z', $payload['event_id']);
        $this->assertNotSame('', $payload['created_at']);
    }

    public function testListReturnsSortedNames(): void
    {
        $storage = new CheckpointStorage($this->tmpDir);
        $storage->create('zebra', '01H');
        $storage->create('alpha', '01H');
        $storage->create('mango', '01H');

        $this->assertSame(['alpha', 'mango', 'zebra'], $storage->list());
    }

    public function testDeleteRemovesCheckpoint(): void
    {
        $storage = new CheckpointStorage($this->tmpDir);
        $storage->create('temp', '01H');
        $this->assertTrue($storage->delete('temp'));
        $this->assertFalse($storage->exists('temp'));
    }

    public function testReadOnMissingThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CheckpointStorage($this->tmpDir))->read('does-not-exist');
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesAreRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CheckpointStorage($this->tmpDir))->create($name, '01H');
    }

    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [' '];
        yield 'pipe' => ['foo|bar'];
        yield 'parent dir' => ['../escape'];
        yield 'wildcard' => ['foo*'];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? @rmdir((string) $file) : @unlink((string) $file);
        }

        @rmdir($dir);
    }
}
