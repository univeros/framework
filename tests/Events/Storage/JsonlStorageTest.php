<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Storage;

use Altair\Events\Actor;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Storage\JsonlStorage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonlStorage::class)]
class JsonlStorageTest extends TestCase
{
    private string $tmpDir;

    private string $logPath;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/altair-events-' . bin2hex(random_bytes(4));
        $this->logPath = $this->tmpDir . '/events.jsonl';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testAppendCreatesParentDirectoryOnDemand(): void
    {
        $this->assertDirectoryDoesNotExist($this->tmpDir);

        $storage = new JsonlStorage($this->logPath);
        $storage->append($this->makeEvent());

        $this->assertFileExists($this->logPath);
        $contents = (string) file_get_contents($this->logPath);
        $this->assertStringEndsWith("\n", $contents);
        $this->assertSame(1, substr_count($contents, "\n"));
    }

    public function testReadAllYieldsAppendedEventsInOrder(): void
    {
        $storage = new JsonlStorage($this->logPath);
        $storage->append($this->makeEvent('01HAAA0000000000000000000A'));
        $storage->append($this->makeEvent('01HAAA0000000000000000000B'));
        $storage->append($this->makeEvent('01HAAA0000000000000000000C'));

        $ids = [];
        foreach ($storage->readAll() as $event) {
            $ids[] = $event->id;
        }

        $this->assertSame(
            ['01HAAA0000000000000000000A', '01HAAA0000000000000000000B', '01HAAA0000000000000000000C'],
            $ids,
        );
    }

    public function testReadReverseYieldsNewestFirst(): void
    {
        $storage = new JsonlStorage($this->logPath);
        $storage->append($this->makeEvent('01HAAA0000000000000000000A'));
        $storage->append($this->makeEvent('01HAAA0000000000000000000B'));

        $ids = [];
        foreach ($storage->readReverse() as $event) {
            $ids[] = $event->id;
        }

        $this->assertSame(['01HAAA0000000000000000000B', '01HAAA0000000000000000000A'], $ids);
    }

    public function testReadAllSkipsMalformedLines(): void
    {
        @mkdir($this->tmpDir, 0775, true);
        file_put_contents(
            $this->logPath,
            "not json\n" .
            $this->makeEvent('01HBBB0000000000000000000A')->toJsonLine() . "\n" .
            "{garbage\n" .
            $this->makeEvent('01HBBB0000000000000000000B')->toJsonLine() . "\n",
        );

        $storage = new JsonlStorage($this->logPath);
        $ids = [];
        foreach ($storage->readAll() as $event) {
            $ids[] = $event->id;
        }

        $this->assertSame(['01HBBB0000000000000000000A', '01HBBB0000000000000000000B'], $ids);
    }

    public function testReadAllOnMissingFileYieldsNothing(): void
    {
        $storage = new JsonlStorage($this->logPath);
        $this->assertSame(0, $storage->count());
        $this->assertSame([], iterator_to_array($storage->readAll(), false));
    }

    public function testCountSkipsBlankLines(): void
    {
        @mkdir($this->tmpDir, 0775, true);
        file_put_contents(
            $this->logPath,
            $this->makeEvent('01HCCC0000000000000000000A')->toJsonLine() . "\n\n" .
            $this->makeEvent('01HCCC0000000000000000000B')->toJsonLine() . "\n",
        );

        $this->assertSame(2, (new JsonlStorage($this->logPath))->count());
    }

    private function makeEvent(?string $id = null): Event
    {
        return new Event(
            id: $id ?? '01HAAA0000000000000000000A',
            timestamp: new \DateTimeImmutable('2026-05-27T10:42:13.000000Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 5,
        );
    }
}
