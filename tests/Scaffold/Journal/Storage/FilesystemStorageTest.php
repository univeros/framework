<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Journal\Storage;

use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\Storage\FilesystemStorage;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilesystemStorage::class)]
class FilesystemStorageTest extends TestCase
{
    private string $tmpDir;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/altair-journal-storage-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }

            @rmdir($this->tmpDir);
        }
    }

    public function testSaveAndLoadRoundTrip(): void
    {
        $storage = new FilesystemStorage($this->tmpDir);
        $entry = $this->entry('2026-05-27T10:00:00Z', 'A');

        $path = $storage->save($entry);
        $this->assertFileExists($path);

        $loaded = $storage->load($entry->id);
        $this->assertSame($entry->id, $loaded->id);
        $this->assertSame($entry->spec, $loaded->spec);
    }

    public function testReadAllYieldsChronologically(): void
    {
        $storage = new FilesystemStorage($this->tmpDir);
        $storage->save($this->entry('2026-05-27T10:00:00Z', 'oldest'));
        $storage->save($this->entry('2026-05-27T12:00:00Z', 'newest'));
        $storage->save($this->entry('2026-05-27T11:00:00Z', 'middle'));

        $ids = [];
        foreach ($storage->readAll() as $entry) {
            $ids[] = $entry->spec['content_inline'];
        }

        $this->assertSame(['oldest', 'middle', 'newest'], $ids);
    }

    public function testReadReverseYieldsNewestFirst(): void
    {
        $storage = new FilesystemStorage($this->tmpDir);
        $storage->save($this->entry('2026-05-27T10:00:00Z', 'oldest'));
        $storage->save($this->entry('2026-05-27T12:00:00Z', 'newest'));

        $ids = [];
        foreach ($storage->readReverse() as $entry) {
            $ids[] = $entry->spec['content_inline'];
        }

        $this->assertSame(['newest', 'oldest'], $ids);
    }

    public function testLoadThrowsForMissingEntry(): void
    {
        $this->expectException(EntryNotFoundException::class);
        (new FilesystemStorage($this->tmpDir))->load('does-not-exist');
    }

    public function testReadAllSkipsMalformedFiles(): void
    {
        @mkdir($this->tmpDir, 0o775, true);
        $storage = new FilesystemStorage($this->tmpDir);
        $storage->save($this->entry('2026-05-27T10:00:00Z', 'good'));

        file_put_contents($this->tmpDir . '/20260527T110000Z-bad.json', '{not json');

        $contents = [];
        foreach ($storage->readAll() as $entry) {
            $contents[] = $entry->spec['content_inline'];
        }

        $this->assertSame(['good'], $contents);
    }

    public function testWriteIsAtomicViaTmpAndRename(): void
    {
        $storage = new FilesystemStorage($this->tmpDir);
        $storage->save($this->entry('2026-05-27T10:00:00Z', 'first'));

        // Should not leave .tmp files behind.
        $stragglers = glob($this->tmpDir . '/*.tmp.*') ?: [];
        $this->assertSame([], $stragglers);
    }

    private function entry(string $when, string $content): JournalEntry
    {
        return JournalEntry::scaffold(
            command: 'bin/altair spec:scaffold spec.yaml',
            specPath: 'spec.yaml',
            specContent: $content,
            scaffoldVersion: '1.0',
            timestamp: new DateTimeImmutable($when),
        );
    }
}
