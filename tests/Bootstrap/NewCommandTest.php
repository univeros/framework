<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\Cli\NewCommand;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewCommand::class)]
final class NewCommandTest extends TestCase
{
    private string $dir;

    #[Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/altair-new-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function testGeneratesARunnableProject(): void
    {
        ob_start();
        $exit = (new NewCommand())(dir: $this->dir, preset: 'minimal');
        ob_end_clean();

        self::assertSame(0, $exit);
        self::assertFileExists($this->dir . '/composer.json');
        self::assertFileExists($this->dir . '/.env');
        self::assertFileExists($this->dir . '/public/index.php');
        self::assertFileExists($this->dir . '/app/Http/Actions/PingAction.php');
    }

    public function testUnknownPresetReturnsExitCodeTwo(): void
    {
        $exit = (new NewCommand())(dir: $this->dir, preset: 'enterprise');

        self::assertSame(2, $exit);
        self::assertDirectoryDoesNotExist($this->dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
