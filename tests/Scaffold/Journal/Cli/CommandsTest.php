<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Journal\Cli;

use Altair\Scaffold\Journal\Cli\DiffCommand;
use Altair\Scaffold\Journal\Cli\HistoryCommand;
use Altair\Scaffold\Journal\Cli\RewindCommand;
use Altair\Scaffold\Journal\Cli\ShowCommand;
use Altair\Scaffold\Journal\FileSnapshot;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\Storage\FilesystemStorage;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HistoryCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(DiffCommand::class)]
#[CoversClass(RewindCommand::class)]
class CommandsTest extends TestCase
{
    private string $projectRoot;

    private Journal $journal;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/altair-journal-cli-' . bin2hex(random_bytes(4));
        @mkdir($this->projectRoot . '/.altair/journal', 0o775, true);
        $this->journal = new Journal(
            new FilesystemStorage($this->projectRoot . '/.altair/journal'),
            $this->projectRoot,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->rrmdir($this->projectRoot);
    }

    public function testHistoryAnnouncesEmptyJournal(): void
    {
        ob_start();
        $exit = (new HistoryCommand($this->journal))(n: 10, since: null, spec: null, format: 'human');
        $output = (string) ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No journal entries', $output);
    }

    public function testHistoryJsonReturnsArray(): void
    {
        $this->journal->record(JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        ));

        ob_start();
        (new HistoryCommand($this->journal))(n: 10, since: null, spec: null, format: 'json');
        $output = (string) ob_get_clean();
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $decoded);
        $this->assertSame('spec.yaml', $decoded[0]['spec_path']);
    }

    public function testShowResolvesByPrefix(): void
    {
        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $this->journal->record($entry);

        ob_start();
        $exit = (new ShowCommand($this->journal))(id: substr($entry->id, 0, 18), format: 'json');
        $output = (string) ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString($entry->id, $output);
    }

    public function testShowReportsNonZeroForUnknownId(): void
    {
        ob_start();
        $exit = (new ShowCommand($this->journal))(id: 'nope', format: 'human');
        ob_get_clean();
        $this->assertSame(1, $exit);
    }

    public function testDiffPrintsCreatedAndModified(): void
    {
        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', hash('sha256', 'foo'), 100)],
            filesModified: [FileSnapshot::modified('config/routes.php', hash('sha256', 'a'), hash('sha256', 'b'), "@@ -1,1 +1,1 @@\n-a\n+b\n", 'a')],
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $this->journal->record($entry);

        ob_start();
        (new DiffCommand($this->journal))(id: $entry->id);
        $output = (string) ob_get_clean();
        $this->assertStringContainsString('+++ src/Foo.php', $output);
        $this->assertStringContainsString('~~~ config/routes.php', $output);
        $this->assertStringContainsString('-a', $output);
    }

    public function testRewindDryRunDoesNotMutateWorkspace(): void
    {
        @mkdir($this->projectRoot . '/src', 0o775, true);
        file_put_contents($this->projectRoot . '/src/Foo.php', '<?php class Foo {}');
        $sha = hash_file('sha256', $this->projectRoot . '/src/Foo.php');

        $entry = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', $sha, 100)],
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $this->journal->record($entry);

        ob_start();
        $exit = (new RewindCommand($this->journal))(to: null, dryRun: true, force: false);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->projectRoot . '/src/Foo.php');
        $this->assertStringContainsString('Would rewind 1', $output);
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
