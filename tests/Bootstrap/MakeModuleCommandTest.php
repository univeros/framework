<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\Cli\MakeModuleCommand;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MakeModuleCommand::class)]
final class MakeModuleCommandTest extends TestCase
{
    private string $target;

    #[Override]
    protected function setUp(): void
    {
        $this->target = sys_get_temp_dir() . '/altair-module-new-' . bin2hex(random_bytes(5));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->target);
    }

    public function testScaffoldsAModulePackageWithDerivedNamespace(): void
    {
        ob_start();
        $exit = (new MakeModuleCommand())(dir: $this->target, name: 'acme/user-management');
        $output = (string) ob_get_clean();

        self::assertSame(0, $exit);

        $composer = json_decode((string) file_get_contents($this->target . '/composer.json'), true);
        self::assertIsArray($composer);
        self::assertSame('acme/user-management', $composer['name']);
        self::assertArrayHasKey('Acme\\UserManagement\\', $composer['autoload']['psr-4']);
        self::assertArrayHasKey('Acme\\UserManagement\\Tests\\', $composer['autoload-dev']['psr-4']);

        $module = (string) file_get_contents($this->target . '/src/Module.php');
        self::assertStringContainsString('namespace Acme\\UserManagement;', $module);
        self::assertStringContainsString('use Acme\\UserManagement\\Http\\Actions\\SampleAction;', $module);
        self::assertStringNotContainsString('VendorModule', $module);

        $migrations = glob($this->target . '/database/migrations/*.php') ?: [];
        self::assertCount(1, $migrations);
        $migration = (string) file_get_contents($migrations[0]);
        self::assertStringContainsString('namespace Acme\\UserManagement\\Database\\Migrations;', $migration);

        // The next-steps blurb tells the user exactly what to register.
        self::assertStringContainsString('Acme\\UserManagement\\Module', $output);
    }

    public function testExplicitNamespaceOverride(): void
    {
        ob_start();
        (new MakeModuleCommand())(dir: $this->target, name: 'acme/billing', namespace: 'Acme\\Billing\\Pro');
        ob_get_clean();

        $module = (string) file_get_contents($this->target . '/src/Module.php');
        self::assertStringContainsString('namespace Acme\\Billing\\Pro;', $module);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
