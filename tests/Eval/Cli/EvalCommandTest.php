<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Eval\Cli;

use Altair\Eval\Cli\EvalCommand;
use Altair\Eval\Configuration\EvalConfiguration;
use Altair\Eval\Support\Json;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvalCommand::class)]
#[CoversClass(Json::class)]
#[CoversClass(EvalConfiguration::class)]
final class EvalCommandTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function testSimpleExpressionReturnsZeroAndPrintsResult(): void
    {
        [$exit, $stdout] = $this->invoke(['snippet' => 'return 1 + 1;']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('int = 2', $stdout);
    }

    public function testJsonFormatEmitsValidStructuredOutput(): void
    {
        [$exit, $stdout] = $this->invoke(['snippet' => 'return 7;', 'format' => 'json']);

        self::assertSame(0, $exit);
        $data = json_decode($stdout, true);
        self::assertTrue($data['ok']);
        self::assertSame(['type' => 'int', 'value' => 7], $data['result']);
    }

    public function testExceptionSnippetExitsOneAndReportsTheClass(): void
    {
        [$exit, $stdout] = $this->invoke(['snippet' => "throw new \\RuntimeException('boom');"]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('RuntimeException', $stdout);
        self::assertStringContainsString('boom', $stdout);
    }

    public function testMissingSnippetExitsTwoWithHint(): void
    {
        [$exit, $stdout] = $this->invoke([]);

        self::assertSame(2, $exit);
        self::assertStringContainsString('--file', $stdout);
    }

    public function testFileOptionReadsSnippetFromDisk(): void
    {
        $file = realpath(__DIR__ . '/../../../') . '/build/altair-eval-cli-' . bin2hex(random_bytes(4)) . '.php';
        $this->tempFiles[] = $file;
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o755, true);
        }

        file_put_contents($file, 'return 99;');

        [$exit, $stdout] = $this->invoke(['file' => $file, 'format' => 'json']);

        self::assertSame(0, $exit);
        self::assertSame(99, json_decode($stdout, true)['result']['value']);
    }

    public function testUnsafeRecordsAnEventThroughTheRecorder(): void
    {
        $recorder = new class implements RecorderInterface {
            /** @var list<Event> */
            public array $events = [];

            #[Override]
            public function record(Event $event): void
            {
                $this->events[] = $event;
            }
        };

        $command = new EvalCommand(recorder: $recorder);

        ob_start();
        $command(snippet: 'return 1;', unsafe: true, format: 'json');
        ob_get_clean();

        self::assertCount(1, $recorder->events);
        self::assertSame('eval', $recorder->events[0]->command);
        self::assertSame('eval', $recorder->events[0]->kind->value);
        self::assertTrue($recorder->events[0]->extra['unsafe']);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array{0: int, 1: string}
     */
    private function invoke(array $args): array
    {
        $command = new EvalCommand();

        ob_start();
        $exit = $command(...$args);
        $stdout = (string) ob_get_clean();

        return [$exit, $stdout];
    }
}
