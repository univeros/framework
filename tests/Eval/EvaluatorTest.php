<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Eval;

use Altair\Eval\EvalRequest;
use Altair\Eval\Evaluator;
use Altair\Eval\EvalResult;
use Altair\Eval\Runner\SubprocessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Full subprocess integration: each test spawns a real PHP subprocess against
 * the framework's own root (its vendor/autoload.php) and asserts that the
 * sandbox actually sandboxes — not just that the flags are present.
 */
#[CoversClass(Evaluator::class)]
#[CoversClass(SubprocessRunner::class)]
#[CoversClass(EvalResult::class)]
final class EvaluatorTest extends TestCase
{
    private string $root;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->root = (string) realpath(__DIR__ . '/../../');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function testReturnValueIsEncodedAndDurationIsRecorded(): void
    {
        $result = $this->evaluate('return 1 + 1;');

        self::assertTrue($result->ok());
        self::assertSame(['type' => 'int', 'value' => 2], $result->result);
        self::assertNull($result->exception);
        self::assertGreaterThanOrEqual(0, $result->durationMs);
    }

    public function testStdoutFromSnippetIsCapturedSeparatelyFromResult(): void
    {
        $result = $this->evaluate("echo 'hello'; return 'world';");

        self::assertSame('hello', $result->stdout);
        self::assertSame('world', $result->result['value']);
    }

    public function testThrownExceptionIsEncodedAndExitCodeIsOne(): void
    {
        $result = $this->evaluate("throw new \\RuntimeException('nope');");

        self::assertFalse($result->ok());
        self::assertNull($result->result);
        self::assertNotNull($result->exception);
        self::assertSame('RuntimeException', $result->exception['class']);
        self::assertSame('nope', $result->exception['message']);
        self::assertSame(1, $result->exitCode);
    }

    public function testRunawayLoopIsKilledAtTimeout(): void
    {
        $result = $this->evaluate('while (true) {}', timeoutMs: EvalRequest::MIN_TIMEOUT_MS);

        self::assertTrue($result->timedOut);
        self::assertSame(124, $result->exitCode);
        self::assertLessThan(2_000, $result->durationMs);
    }

    public function testDisabledFunctionsBlockProcessExec(): void
    {
        $result = $this->evaluate("return exec('echo blocked');");

        self::assertFalse($result->ok());
        // PHP renders disabled-function calls as a warning to stderr, then continues
        // with `null` — or, under strict configs, as an Error. Either signal proves
        // the sandbox blocked it.
        self::assertTrue(
            str_contains($result->stderr, 'disabled') || $result->exception !== null,
            'expected disabled-function evidence in stderr or as an exception: ' . $result->stderr,
        );
    }

    public function testOpenBasedirBlocksWritesOutsideProjectRoot(): void
    {
        $forbidden = sys_get_temp_dir() . '/altair-eval-must-not-exist-' . bin2hex(random_bytes(4)) . '.txt';
        $snippet = \sprintf("@file_put_contents(%s, 'leak'); return is_file(%s);", var_export($forbidden, true), var_export($forbidden, true));

        $result = $this->evaluate($snippet);

        self::assertSame(['type' => 'bool', 'value' => false], $result->result);
        self::assertFileDoesNotExist($forbidden);
    }

    public function testContainerHelperResolvesBindingsFromTheHostBootstrap(): void
    {
        // The bootstrap must live inside the project root: open_basedir
        // confinement is exactly what blocks it being read from /tmp.
        $buildDir = $this->root . '/build';
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0o755, true);
        }

        $bootstrap = $buildDir . '/altair-eval-bootstrap-' . bin2hex(random_bytes(4)) . '.php';
        $this->tempFiles[] = $bootstrap;
        file_put_contents($bootstrap, <<<'PHP'
            <?php
            $c = new \Altair\Container\Container();
            $c->value('answer', 42);
            return $c;
            PHP);

        $result = $this->evaluate("return container('answer');", bootstrap: $bootstrap);

        self::assertSame(['type' => 'int', 'value' => 42], $result->result);
    }

    public function testMissingBootstrapFallsBackToBareContainer(): void
    {
        $result = $this->evaluate("return container() instanceof \\Altair\\Container\\Container;");

        self::assertSame(['type' => 'bool', 'value' => true], $result->result);
    }

    public function testStructuralInjectionInSnippetCannotReachWrapperFileScope(): void
    {
        // C1 regression: an embedded snippet that closed the wrapper closure
        // with `})();` used to inject statements into the wrapper's file scope
        // and could `symlink()` the result file to a path outside open_basedir.
        // The snippet is now delivered via a separate `require`-ed file, so
        // the same payload is just a parse error inside that file — captured
        // as an exception, nothing executed at file scope.
        $forbidden = sys_get_temp_dir() . '/altair-eval-must-not-leak-' . bin2hex(random_bytes(4)) . '.txt';
        $hostile = \sprintf(
            "})(); @symlink(%s, %s); \$_ = (static function() {",
            var_export('/etc/passwd', true),
            var_export($forbidden, true),
        );

        $result = $this->evaluate($hostile);

        self::assertFileDoesNotExist($forbidden);
        self::assertNotNull($result->exception, 'a syntactically-broken snippet must surface as a captured exception');
    }

    private function evaluate(string $snippet, int $timeoutMs = EvalRequest::DEFAULT_TIMEOUT_MS, ?string $bootstrap = null): EvalResult
    {
        return (new Evaluator())->evaluate(new EvalRequest(
            $snippet,
            $this->root,
            $timeoutMs,
            bootstrap: $bootstrap,
        ));
    }
}
