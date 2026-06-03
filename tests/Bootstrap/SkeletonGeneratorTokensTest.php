<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\SkeletonGenerator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Covers the configurable placeholder tokens that let the same generator emit a
 * module package skeleton (`VendorModule` / `vendor/module`) as well as the app
 * skeleton (`App` / `vendor/app`).
 */
#[CoversClass(SkeletonGenerator::class)]
final class SkeletonGeneratorTokensTest extends TestCase
{
    private string $skeleton;

    private string $target;

    #[Override]
    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/altair-tok-' . bin2hex(random_bytes(4));
        $this->skeleton = $base . '/skeleton';
        $this->target = $base . '/out';
        mkdir($this->skeleton . '/src', 0o775, true);

        file_put_contents($this->skeleton . '/composer.json', <<<'JSON'
            {
                "name": "vendor/module",
                "autoload": { "psr-4": { "VendorModule\\": "src/" } }
            }
            JSON);
        file_put_contents($this->skeleton . '/src/Greeter.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace VendorModule;

            use VendorModule\Support\Helper;

            final class Greeter {}
            PHP);
        file_put_contents($this->skeleton . '/README.md', "Install: `composer require vendor/module`\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['out/src', 'out', 'skeleton/src', 'skeleton'] as $sub) {
            $dir = \dirname($this->skeleton) . '/' . $sub;
            array_map(unlink(...), glob($dir . '/*') ?: []);
            @rmdir($dir);
        }
    }

    public function testRewritesModuleTokens(): void
    {
        (new SkeletonGenerator())->generate(
            $this->target,
            skeletonDir: $this->skeleton,
            namespace: 'Acme\\UserManagement',
            projectName: 'acme/user-management',
            placeholderNamespace: 'VendorModule',
            placeholderPackageName: 'vendor/module',
        );

        $composer = json_decode((string) file_get_contents($this->target . '/composer.json'), true);
        self::assertIsArray($composer);
        self::assertSame('acme/user-management', $composer['name']);
        self::assertArrayHasKey('Acme\\UserManagement\\', $composer['autoload']['psr-4']);

        $php = (string) file_get_contents($this->target . '/src/Greeter.php');
        self::assertStringContainsString('namespace Acme\\UserManagement;', $php);
        self::assertStringContainsString('Acme\\UserManagement\\Support\\Helper', $php);
        self::assertStringNotContainsString('VendorModule', $php);

        $readme = (string) file_get_contents($this->target . '/README.md');
        self::assertStringContainsString('composer require acme/user-management', $readme);
    }
}
