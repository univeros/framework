<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Journal;

use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\Exception\RewindRefusedException;
use Altair\Scaffold\Journal\FileSnapshot;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\Storage\FilesystemStorage;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Journal::class)]
class JournalRewindTest extends TestCase
{
    private string $projectRoot;

    private string $journalDir;

    private Journal $journal;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/altair-journal-rewind-' . bin2hex(random_bytes(4));
        $this->journalDir = $this->projectRoot . '/.altair/journal';
        @mkdir($this->projectRoot, 0o775, true);
        $this->journal = new Journal(new FilesystemStorage($this->journalDir), $this->projectRoot);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->rrmdir($this->projectRoot);
    }

    public function testRewindDeletesCreatedFilesAndRestoresModifiedFiles(): void
    {
        // Setup: simulate that scaffold created src/Foo.php and modified config/routes.php.
        @mkdir($this->projectRoot . '/src', 0o775, true);
        @mkdir($this->projectRoot . '/config', 0o775, true);

        file_put_contents($this->projectRoot . '/src/Foo.php', "<?php class Foo {}");
        file_put_contents($this->projectRoot . '/config/routes.php', "<?php\nreturn ['new line'];");

        $shaAfterCreated = hash_file('sha256', $this->projectRoot . '/src/Foo.php');
        $shaAfterModified = hash_file('sha256', $this->projectRoot . '/config/routes.php');
        $contentBeforeModified = "<?php\nreturn [];";

        $entry = JournalEntry::scaffold(
            command: 'bin/altair spec:scaffold spec.yaml',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', $shaAfterCreated, 100)],
            filesModified: [FileSnapshot::modified(
                'config/routes.php',
                hash('sha256', $contentBeforeModified),
                $shaAfterModified,
                '@@ diff @@',
                $contentBeforeModified,
            )],
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );

        $this->journal->record($entry);
        $result = $this->journal->rewind($entry);

        $this->assertFileDoesNotExist($this->projectRoot . '/src/Foo.php');
        $this->assertSame($contentBeforeModified, (string) file_get_contents($this->projectRoot . '/config/routes.php'));
        $this->assertCount(1, $result['deleted']);
        $this->assertCount(1, $result['restored']);
        $this->assertCount(0, $result['skipped']);

        // Entry still on disk, now with reverted_at stamped.
        $reloaded = $this->journal->findById($entry->id);
        $this->assertTrue($reloaded->isReverted());
    }

    public function testRewindRefusesWhenGeneratedFileWasHandEdited(): void
    {
        @mkdir($this->projectRoot . '/src', 0o775, true);
        file_put_contents($this->projectRoot . '/src/Foo.php', "<?php class Foo {}");
        $shaAfter = hash_file('sha256', $this->projectRoot . '/src/Foo.php');

        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', $shaAfter, 100)],
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $this->journal->record($entry);

        // User hand-edits the file.
        file_put_contents($this->projectRoot . '/src/Foo.php', "<?php class FooEdited {}");

        try {
            $this->journal->rewind($entry, force: false);
            $this->fail('Expected RewindRefusedException.');
        } catch (RewindRefusedException $rewindRefusedException) {
            $this->assertSame(['src/Foo.php'], $rewindRefusedException->unsafePaths);
            // Hand-edited file must NOT have been deleted.
            $this->assertFileExists($this->projectRoot . '/src/Foo.php');
        }
    }

    public function testRewindForceClobbersHandEdits(): void
    {
        @mkdir($this->projectRoot . '/src', 0o775, true);
        file_put_contents($this->projectRoot . '/src/Foo.php', "<?php class Foo {}");
        $shaAfter = hash_file('sha256', $this->projectRoot . '/src/Foo.php');

        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', $shaAfter, 100)],
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $this->journal->record($entry);
        file_put_contents($this->projectRoot . '/src/Foo.php', "<?php class FooEdited {}");

        $result = $this->journal->rewind($entry, force: true);
        $this->assertContains('src/Foo.php', $result['deleted']);
        $this->assertFileDoesNotExist($this->projectRoot . '/src/Foo.php');
    }

    public function testFindByIdResolvesPrefix(): void
    {
        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $this->journal->record($entry);

        $prefix = substr($entry->id, 0, 18); // timestamp portion alone — unambiguous in a 1-entry journal
        $found = $this->journal->findById($prefix);
        $this->assertSame($entry->id, $found->id);
    }

    public function testRewindRejectsAlreadyRevertedEntry(): void
    {
        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        )->withRevertedAt(new DateTimeImmutable('now'));
        $this->journal->record($entry);

        $this->expectException(JournalException::class);
        $this->journal->rewind($entry);
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
