<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Cli;

use Altair\MigrationIntelligence\Cli\PlanCommand;
use Altair\MigrationIntelligence\Db\DatabaseProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlanCommand::class)]
#[CoversClass(DatabaseProbe::class)]
class PlanCommandTest extends TestCase
{
    private string $workspace = '';

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/mig_cli_' . uniqid('', true);
        mkdir($this->workspace, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workspace . '/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->workspace);
    }

    public function testSpecVsSpecPrintsAddColumn(): void
    {
        $from = $this->writeSpec('v1.yaml', "      email: { type: string }");
        $to = $this->writeSpec('v2.yaml', "      email: { type: string }\n      display_name: { type: string, nullable: true }");

        [$output, $exit] = $this->invokePlan(fromSpec: $from, toSpec: $to);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('ADD COLUMN', $output);
        $this->assertStringContainsString('display_name', $output);
    }

    public function testSpecVsSpecJsonFormat(): void
    {
        $from = $this->writeSpec('v1.yaml', "      email: { type: string }");
        $to = $this->writeSpec('v2.yaml', "      email: { type: string }\n      nickname: { type: string, nullable: true }");

        [$output, $exit] = $this->invokePlan(fromSpec: $from, toSpec: $to, format: 'json');

        $decoded = json_decode($output, true);
        $this->assertSame(0, $exit);
        $this->assertIsArray($decoded);
        $this->assertSame('users', $decoded['table']);
        $this->assertSame('add_column', $decoded['migrations'][0]['operations'][0]['op']);
    }

    public function testWritesMigrationFileToOutputDir(): void
    {
        $from = $this->writeSpec('v1.yaml', "      email: { type: string }");
        $to = $this->writeSpec('v2.yaml', "      email: { type: string }\n      note: { type: text, nullable: true }");
        $outputDir = $this->workspace . '/out';

        [$output, $exit] = $this->invokePlan(fromSpec: $from, toSpec: $to, output: $outputDir);

        $this->assertSame(0, $exit);
        $written = glob($outputDir . '/*.php') ?: [];
        $this->assertCount(1, $written);
        $this->assertStringContainsString('extends Migration', (string) file_get_contents($written[0]));
        $this->assertStringContainsString('wrote ', $output);

        foreach ($written as $file) {
            unlink($file);
        }

        @rmdir($outputDir);
    }

    public function testUnknownFormatReturnsTwo(): void
    {
        $from = $this->writeSpec('v1.yaml', "      email: { type: string }");
        $to = $this->writeSpec('v2.yaml', "      email: { type: string }");

        [, $exit] = $this->invokePlan(fromSpec: $from, toSpec: $to, format: 'xml');

        $this->assertSame(2, $exit);
    }

    public function testMissingSourceReturnsTwo(): void
    {
        [$output, $exit] = $this->invokePlan();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('--from-entity', $output);
    }

    public function testSpecVsDatabaseWithoutDatabaseReturnsTwo(): void
    {
        $spec = $this->writeSpec('v1.yaml', "      email: { type: string }");
        // DatabaseProbe with empty env never connects.
        $command = new PlanCommand(probe: new DatabaseProbe([]));

        ob_start();
        $exit = $command($spec);
        $output = (string) ob_get_clean();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('No database reachable', $output);
    }

    /**
     * @return array{string, int}
     */
    private function invokePlan(
        ?string $spec = null,
        ?string $fromSpec = null,
        ?string $toSpec = null,
        ?string $output = null,
        string $format = 'human',
    ): array {
        $command = new PlanCommand(probe: new DatabaseProbe([]));

        ob_start();
        $exit = $command(
            spec: $spec,
            fromSpec: $fromSpec,
            toSpec: $toSpec,
            output: $output,
            format: $format,
        );

        return [(string) ob_get_clean(), $exit];
    }

    private function writeSpec(string $name, string $fieldLines): string
    {
        $path = $this->workspace . '/' . $name;
        $yaml = <<<YAML
            endpoint:
              method: POST
              path: /users
            domain:
              class: App\\CreateUser
            persistence:
              entity:
                class: App\\User
                table: users
                fields:
                  id: { type: integer, primary: true }
            {$fieldLines}
              repository: App\\UserRepository
            YAML;

        file_put_contents($path, $yaml);

        return $path;
    }
}
