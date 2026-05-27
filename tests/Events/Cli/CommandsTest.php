<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Cli;

use Altair\Events\Actor;
use Altair\Events\Changes;
use Altair\Events\Cli\CheckpointCreateCommand;
use Altair\Events\Cli\CheckpointDeleteCommand;
use Altair\Events\Cli\CheckpointDiffCommand;
use Altair\Events\Cli\CheckpointListCommand;
use Altair\Events\Cli\CompactCommand;
use Altair\Events\Cli\FilterCommand;
use Altair\Events\Cli\OutputRenderer;
use Altair\Events\Cli\ShowCommand;
use Altair\Events\Cli\SinceCommand;
use Altair\Events\Cli\SinceLastSuccessCommand;
use Altair\Events\Cli\StatsCommand;
use Altair\Events\Cli\TailCommand;
use Altair\Events\Configuration\EventsSettings;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use Altair\Events\Recorder;
use Altair\Events\Scrubber;
use Altair\Events\Storage\CheckpointStorage;
use Altair\Events\Storage\JsonlStorage;
use Altair\Events\Storage\SnapshotStorage;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TailCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(SinceCommand::class)]
#[CoversClass(SinceLastSuccessCommand::class)]
#[CoversClass(FilterCommand::class)]
#[CoversClass(StatsCommand::class)]
#[CoversClass(CheckpointCreateCommand::class)]
#[CoversClass(CheckpointListCommand::class)]
#[CoversClass(CheckpointDeleteCommand::class)]
#[CoversClass(CheckpointDiffCommand::class)]
#[CoversClass(CompactCommand::class)]
#[CoversClass(OutputRenderer::class)]
class CommandsTest extends TestCase
{
    private string $tmpRoot;

    private EventsSettings $settings;

    private JsonlStorage $storage;

    private CheckpointStorage $checkpoints;

    private SnapshotStorage $snapshots;

    private Recorder $recorder;

    private Reader $reader;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/altair-events-cli-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpRoot, 0775, true);

        $this->settings = new EventsSettings(
            enabled: true,
            projectRoot: $this->tmpRoot,
            baseDirectory: '.altair',
            logFileName: 'events.jsonl',
            snapshotsDirectory: 'snapshots',
            checkpointsDirectory: 'checkpoints',
        );

        $this->storage = new JsonlStorage($this->settings->logPath());
        $this->snapshots = new SnapshotStorage($this->settings->snapshotsPath());
        $this->checkpoints = new CheckpointStorage($this->settings->checkpointsPath());
        $this->recorder = new Recorder($this->storage, new Scrubber());
        $this->reader = new Reader($this->storage);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpRoot);
    }

    public function testTailHumanModeAnnouncesEmptyLog(): void
    {
        $command = new TailCommand($this->reader);
        ob_start();
        $exit = $command(20, 'human');
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No events recorded yet.', $output);
    }

    public function testTailJsonModeProducesNdjson(): void
    {
        $this->recorder->record($this->makeEvent('01HAAA0000000000000000000A'));
        $this->recorder->record($this->makeEvent('01HAAA0000000000000000000B'));

        ob_start();
        (new TailCommand($this->reader))(10, 'json');
        $output = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", $output)));
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertJson($line);
        }
    }

    public function testShowReturnsNonZeroForMissingId(): void
    {
        ob_start();
        $exit = (new ShowCommand($this->reader, $this->snapshots))('does-not-exist', 'human');
        $output = (string) ob_get_clean();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString("Event 'does-not-exist' not found.", $output);
    }

    public function testShowEmitsHumanDetailIncludingChanges(): void
    {
        $event = new Event(
            id: '01HSHO0000000000000000000A',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 5,
            changes: new Changes(['created' => ['a.php']]),
        );
        $this->recorder->record($event);

        ob_start();
        (new ShowCommand($this->reader, $this->snapshots))('01HSHO0000000000000000000A', 'human');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('id:         01HSHO0000000000000000000A', $output);
        $this->assertStringContainsString('changes:', $output);
        $this->assertStringContainsString('- a.php', $output);
    }

    public function testSinceCommandResolvesEventIdLookup(): void
    {
        $this->recorder->record($this->makeEvent('01HSNN0000000000000000000A'));
        $this->recorder->record($this->makeEvent('01HSNN0000000000000000000B'));
        $this->recorder->record($this->makeEvent('01HSNN0000000000000000000C'));

        ob_start();
        (new SinceCommand($this->reader))('01HSNN0000000000000000000A', 'json');
        $output = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", $output)));
        $this->assertCount(2, $lines);
    }

    public function testSinceLastSuccessReportsNothingOnFreshLog(): void
    {
        ob_start();
        (new SinceLastSuccessCommand($this->reader))('human');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('No events', $output);
    }

    public function testFilterByKind(): void
    {
        $this->recorder->record($this->makeEvent('01HFTN0000000000000000000A', kind: EventKind::Scaffold));
        $this->recorder->record($this->makeEvent('01HFTN0000000000000000000B', kind: EventKind::Migration));

        ob_start();
        (new FilterCommand($this->reader))('scaffold', null, 'json');
        $output = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", $output)));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('01HFTN0000000000000000000A', $lines[0]);
    }

    public function testFilterRejectsUnknownKind(): void
    {
        ob_start();
        $exit = (new FilterCommand($this->reader))('not-a-kind', null, 'human');
        ob_get_clean();
        $this->assertSame(2, $exit);
    }

    public function testStatsJsonOutputContainsExpectedFields(): void
    {
        $this->recorder->record($this->makeEvent('01HSTT0000000000000000000A', kind: EventKind::Scaffold, durationMs: 10));
        $this->recorder->record($this->makeEvent('01HSTT0000000000000000000B', kind: EventKind::Migration, durationMs: 20));

        ob_start();
        (new StatsCommand($this->reader))('json');
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $decoded['total']);
        $this->assertSame(30, $decoded['total_duration_ms']);
        $this->assertSame(['migration' => 1, 'scaffold' => 1], $decoded['by_kind']);
    }

    public function testCheckpointCreateListDeleteDiffRoundTrip(): void
    {
        $this->recorder->record($this->makeEvent('01HCKP0000000000000000000A'));

        ob_start();
        (new CheckpointCreateCommand($this->reader, $this->checkpoints))('milestone-1');
        ob_get_clean();

        $this->assertTrue($this->checkpoints->exists('milestone-1'));

        // Record an event after the checkpoint.
        $this->recorder->record($this->makeEvent('01HCKP0000000000000000000B'));

        ob_start();
        (new CheckpointDiffCommand($this->reader, $this->checkpoints))('milestone-1', 'json');
        $diffOutput = (string) ob_get_clean();
        $lines = array_values(array_filter(explode("\n", $diffOutput)));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('01HCKP0000000000000000000B', $lines[0]);

        ob_start();
        (new CheckpointListCommand($this->checkpoints))('json');
        $listOutput = (string) ob_get_clean();
        $list = json_decode($listOutput, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $list);
        $this->assertSame('milestone-1', $list[0]['name']);

        ob_start();
        $exit = (new CheckpointDeleteCommand($this->checkpoints))('milestone-1');
        ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertFalse($this->checkpoints->exists('milestone-1'));
    }

    public function testCompactArchivesOldEvents(): void
    {
        // Three events older than 30 days, one recent.
        $old = (new DateTimeImmutable('-45 days'));
        $now = (new DateTimeImmutable('now'));

        $this->storage->append($this->makeEvent('01HCMP0000000000000000000A', timestamp: $old));
        $this->storage->append($this->makeEvent('01HCMP0000000000000000000B', timestamp: $old));
        $this->storage->append($this->makeEvent('01HCMP0000000000000000000C', timestamp: $now));

        ob_start();
        $exit = (new CompactCommand($this->storage))(before: null, dryRun: false);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Archived 2 event(s)', $output);
        $this->assertSame(1, $this->storage->count());

        $archiveFile = \dirname($this->settings->logPath()) . '/events.archive/' . $old->format('Y-m') . '.jsonl.gz';
        $this->assertFileExists($archiveFile);
    }

    public function testCompactDryRunDoesNotMutateLog(): void
    {
        $old = new DateTimeImmutable('-100 days');
        $this->storage->append($this->makeEvent('01HCMQ0000000000000000000A', timestamp: $old));

        ob_start();
        (new CompactCommand($this->storage))(before: null, dryRun: true);
        ob_get_clean();

        $this->assertSame(1, $this->storage->count());
    }

    private function makeEvent(
        string $id,
        ?DateTimeImmutable $timestamp = null,
        EventKind $kind = EventKind::Scaffold,
        int $durationMs = 5,
    ): Event {
        return new Event(
            id: $id,
            timestamp: $timestamp ?? new DateTimeImmutable('now'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: $kind,
            status: EventStatus::Ok,
            durationMs: $durationMs,
        );
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
