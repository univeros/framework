<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Eval\Runner;

use Altair\Eval\EvalRequest;
use Altair\Eval\Runner\WrapperBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WrapperBuilder::class)]
final class WrapperBuilderTest extends TestCase
{
    public function testWrapperOpensWithPhpTagAndStrictTypes(): void
    {
        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest('return 1;', '/srv/app'),
            '/tmp/result.json',
            '/tmp/snippet.php',
        );

        self::assertStringStartsWith('<?php', $wrapper);
        self::assertStringContainsString('declare(strict_types=1);', $wrapper);
    }

    public function testWrapperDoesNotEmbedTheSnippetButRequiresItFromASeparateFile(): void
    {
        // C1 regression guard: an embedded snippet that contains a closure
        // terminator can inject statements into the wrapper's file scope.
        // Delivering the snippet via a separate `require` keeps it in its own
        // file-scope and makes that injection impossible.
        $hostile = "})(); symlink('/etc/passwd', \$__altairEvalResultFile); \$_ = (static function() {";

        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest($hostile, '/srv/app'),
            '/tmp/result.json',
            '/tmp/snippet.php',
        );

        self::assertStringNotContainsString($hostile, $wrapper);
        self::assertStringContainsString("'/tmp/snippet.php'", $wrapper);
        self::assertStringContainsString('return require', $wrapper);
    }

    public function testWrapperEncodesProjectRootAndResultPathAsLiterals(): void
    {
        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest('return 1;', '/srv/the/app'),
            '/tmp/result-X.json',
            '/tmp/snippet-X.php',
        );

        // var_export() literals — quoted strings, so they survive an open_basedir + filesystem reroot intact.
        self::assertStringContainsString("'/srv/the/app'", $wrapper);
        self::assertStringContainsString("'/tmp/result-X.json'", $wrapper);
    }

    public function testWrapperResolvesAnExplicitBootstrapPath(): void
    {
        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest('return 1;', '/srv/app', bootstrap: '/srv/app/bootstrap.php'),
            '/tmp/result.json',
            '/tmp/snippet.php',
        );

        self::assertStringContainsString("'/srv/app/bootstrap.php'", $wrapper);
    }

    public function testWrapperFallsBackToTheSkeletonContainerConvention(): void
    {
        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest('return 1;', '/srv/app'),
            '/tmp/result.json',
            '/tmp/snippet.php',
        );

        self::assertStringContainsString("config/container.php", $wrapper);
        self::assertStringContainsString('ALTAIR_EVAL_BOOTSTRAP', $wrapper);
    }

    public function testWrapperProvidesAContainerHelper(): void
    {
        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest('return 1;', '/srv/app'),
            '/tmp/result.json',
            '/tmp/snippet.php',
        );

        self::assertStringContainsString('function container(', $wrapper);
    }

    public function testWrapperIsValidPhpSyntax(): void
    {
        $wrapper = (new WrapperBuilder())->build(
            new EvalRequest("return 1 + 1;", '/srv/app'),
            '/tmp/result.json',
            '/tmp/snippet.php',
        );

        $tmp = (string) tempnam(sys_get_temp_dir(), 'eval-wrapper');
        try {
            file_put_contents($tmp, $wrapper);
            $output = (string) shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1');
            self::assertStringContainsString('No syntax errors', $output, $output);
        } finally {
            @unlink($tmp);
        }
    }
}
