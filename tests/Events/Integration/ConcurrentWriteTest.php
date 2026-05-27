<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Integration;

use Altair\Events\Actor;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Storage\JsonlStorage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonlStorage::class)]
class ConcurrentWriteTest extends TestCase
{
    private string $tmpDir;

    private string $logPath;

    #[Override]
    protected function setUp(): void
    {
        if (!\function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork is not available on this platform.');
        }

        $this->tmpDir = sys_get_temp_dir() . '/altair-events-concurrent-' . bin2hex(random_bytes(4));
        $this->logPath = $this->tmpDir . '/events.jsonl';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->logPath) && is_file($this->logPath)) {
            unlink($this->logPath);
        }
        if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testParallelAppendsDoNotCorruptLog(): void
    {
        $workers = 4;
        $perWorker = 25;

        $pids = [];
        for ($w = 0; $w < $workers; $w++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed.');
            }

            if ($pid === 0) {
                // Child: write $perWorker events then exit.
                $storage = new JsonlStorage($this->logPath);
                for ($i = 0; $i < $perWorker; $i++) {
                    $storage->append(new Event(
                        id: \sprintf('01H%05dWORKER%020d', $w, $i),
                        timestamp: new \DateTimeImmutable('now'),
                        actor: Actor::Cli,
                        command: "bin/altair foo worker={$w} iter={$i}",
                        kind: EventKind::Scaffold,
                        status: EventStatus::Ok,
                        durationMs: 1,
                    ));
                }
                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Re-open storage in parent and read everything back.
        $storage = new JsonlStorage($this->logPath);
        $events = iterator_to_array($storage->readAll(), false);

        $this->assertCount(
            $workers * $perWorker,
            $events,
            'Every appended event must round-trip cleanly (proves no torn writes).',
        );

        // Each line must be valid JSON — readAll already filters bad lines,
        // so we additionally check the raw file has the expected line count.
        $rawLines = (array) file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount($workers * $perWorker, $rawLines);
        foreach ($rawLines as $raw) {
            $this->assertJson((string) $raw);
        }
    }
}
