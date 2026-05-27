<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Cli;

use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Cli\DoctorCommand;
use Altair\Doctor\Doctor;
use Altair\Doctor\Output\RendererRegistry;
use Altair\Doctor\Result\CheckResult;
use Altair\Tests\Doctor\Support\FakeCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctorCommand::class)]
class DoctorCommandTest extends TestCase
{
    public function testJsonOutputAndExitCodeReflectWorstStatus(): void
    {
        $command = $this->command([
            new FakeCheck('a', CheckResult::ok('a', 'ok')),
            new FakeCheck('b', CheckResult::error('b', 'boom')),
        ]);

        ob_start();
        $exit = $command(format: 'json');
        $output = (string) ob_get_clean();

        $this->assertSame(2, $exit);
        $this->assertJson(trim($output));
        $this->assertStringContainsString('"status": "error"', $output);
    }

    public function testHumanOutputForAllOkExitsZero(): void
    {
        $command = $this->command([new FakeCheck('a', CheckResult::ok('a', 'fine'))]);

        ob_start();
        $exit = $command(format: 'human');
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('[ok', $output);
    }

    public function testUnknownFormatExitsTwoWithMessage(): void
    {
        $command = $this->command([new FakeCheck('a', CheckResult::ok('a', 'fine'))]);

        ob_start();
        $exit = $command(format: 'xml');
        $output = (string) ob_get_clean();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString("Unknown output format 'xml'", $output);
    }

    public function testOnlyFlagIsForwarded(): void
    {
        $command = $this->command([
            new FakeCheck('a', CheckResult::error('a', 'boom')),
            new FakeCheck('b', CheckResult::ok('b', 'ok')),
        ]);

        ob_start();
        $exit = $command(format: 'json', only: 'b');
        ob_get_clean();

        $this->assertSame(0, $exit, 'only=b skips the failing a, so exit is 0');
    }

    /**
     * @param list<FakeCheck> $checks
     */
    private function command(array $checks): DoctorCommand
    {
        return new DoctorCommand(new Doctor(new CheckRegistry($checks)), RendererRegistry::default());
    }
}
