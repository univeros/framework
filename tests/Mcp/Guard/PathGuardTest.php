<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Guard;

use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Guard\PathGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathGuard::class)]
final class PathGuardTest extends TestCase
{
    private const string ROOT = '/srv/project';

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenPaths(): iterable
    {
        yield 'vendor relative' => ['vendor/foo/bar.php'];
        yield 'vendor absolute' => ['/srv/project/vendor/x.php'];
        yield 'git dir' => ['.git/config'];
        yield 'composer.json' => ['composer.json'];
        yield 'composer.lock' => ['composer.lock'];
        yield 'dotenv' => ['.env'];
        yield 'dotenv local' => ['.env.local'];
        yield 'nested dotenv' => ['config/.env.production'];
        yield 'escape root' => ['../outside.php'];
        yield 'traversal into vendor' => ['api/../vendor/evil.php'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedPaths(): iterable
    {
        yield 'spec' => ['api/users/create.yaml'];
        yield 'source' => ['app/Domain/User.php'];
        yield 'nested' => ['src/Foo/Bar.php'];
        yield 'env sample is fine' => ['docs/env-notes.md'];
    }

    #[DataProvider('forbiddenPaths')]
    public function testForbiddenPathsAreBlocked(string $path): void
    {
        self::assertTrue((new PathGuard(self::ROOT))->isForbidden($path));
    }

    #[DataProvider('allowedPaths')]
    public function testAllowedPathsPass(string $path): void
    {
        self::assertFalse((new PathGuard(self::ROOT))->isForbidden($path));
    }

    public function testAssertWritableThrowsForForbidden(): void
    {
        $this->expectException(GuardrailException::class);

        (new PathGuard(self::ROOT))->assertWritable('vendor/x.php');
    }

    public function testAssertWritablePassesForAllowed(): void
    {
        (new PathGuard(self::ROOT))->assertWritable('api/users/create.yaml');

        $this->expectNotToPerformAssertions();
    }
}
