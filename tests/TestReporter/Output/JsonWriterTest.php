<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Output;

use Altair\TestReporter\Output\JsonWriter;
use Altair\TestReporter\Resolver\SourceUnderTestResolver;
use Altair\TestReporter\ResultCollector;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonWriter::class)]
class JsonWriterTest extends TestCase
{
    private string $tmpFile;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/altair-testreporter-' . bin2hex(random_bytes(4)) . '.json';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testEmitsJsonToStdout(): void
    {
        $report = (new ResultCollector(new SourceUnderTestResolver(\dirname(__DIR__, 2))))->build('11.5.0');

        ob_start();
        (new JsonWriter())->emit($report);
        $output = (string) ob_get_clean();

        $this->assertJson(trim($output));
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('pass', $decoded['result']);
    }

    public function testEmitsJsonToFile(): void
    {
        $report = (new ResultCollector(new SourceUnderTestResolver(\dirname(__DIR__, 2))))->build('11.5.0');
        (new JsonWriter($this->tmpFile))->emit($report);

        $this->assertFileExists($this->tmpFile);
        $decoded = json_decode((string) file_get_contents($this->tmpFile), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('pass', $decoded['result']);
    }

    public function testOutputIsDeterministic(): void
    {
        $report = (new ResultCollector(new SourceUnderTestResolver(\dirname(__DIR__, 2))))->build('11.5.0');

        ob_start();
        (new JsonWriter())->emit($report);
        $first = (string) ob_get_clean();

        ob_start();
        (new JsonWriter())->emit($report);
        $second = (string) ob_get_clean();

        $this->assertSame($first, $second, 'Same report instance must produce byte-identical output.');
    }
}
