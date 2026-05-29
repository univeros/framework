<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Runner;

use Altair\Eval\EvalRequest;

/**
 * Generates the PHP source the subprocess executes.
 *
 * The wrapper requires the host's Composer autoloader, resolves a `Container`
 * (by explicit bootstrap path, by `ALTAIR_EVAL_BOOTSTRAP` env, by the
 * `config/container.php` skeleton convention, or — failing all three — a bare
 * framework container), provides a `container()` helper, then `require`s the
 * snippet from a separate file inside an isolated closure and writes a
 * structured result payload to a dedicated result file before exiting.
 *
 * The snippet is delivered via a **separate file** rather than embedded into
 * the wrapper source. This is a security boundary: an embedded snippet that
 * closes the wrapper closure with `})();` could inject statements into the
 * wrapper's file scope and bypass `open_basedir` (e.g. by `symlink()`-ing
 * the result file path before the wrapper writes to it). A snippet loaded by
 * `require` cannot escape its own file boundary into the wrapper, so even a
 * pathological snippet stays inside the closure's return-value contract.
 */
final readonly class WrapperBuilder
{
    public function build(EvalRequest $request, string $resultFile, string $snippetFile): string
    {
        $root = var_export($request->projectRoot, true);
        $result = var_export($resultFile, true);
        $snippet = var_export($snippetFile, true);
        $bootstrap = var_export($request->bootstrap, true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            \$__altairEvalRoot        = {$root};
            \$__altairEvalResultFile  = {$result};
            \$__altairEvalSnippetFile = {$snippet};
            \$__altairEvalBootstrap   = {$bootstrap};

            \$autoload = \$__altairEvalRoot . '/vendor/autoload.php';
            if (!is_file(\$autoload)) {
                fwrite(STDERR, 'Composer autoloader not found at ' . \$autoload . "\\n");
                exit(2);
            }
            require \$autoload;

            \$__altairEvalContainer = (static function (string \$root, ?string \$bootstrap): \Altair\Container\Container {
                \$env = getenv('ALTAIR_EVAL_BOOTSTRAP');
                \$path = \$bootstrap ?? (is_string(\$env) && \$env !== '' ? \$env : null);
                if (\$path === null) {
                    \$candidate = \$root . '/config/container.php';
                    if (is_file(\$candidate)) {
                        \$path = \$candidate;
                    }
                }
                if (\$path !== null && is_file(\$path)) {
                    \$loaded = require \$path;
                    if (\$loaded instanceof \Altair\Container\Container) {
                        return \$loaded;
                    }
                }

                return new \Altair\Container\Container();
            })(\$__altairEvalRoot, \$__altairEvalBootstrap);

            if (!function_exists('container')) {
                function container(?string \$id = null): mixed
                {
                    \$GLOBALS['__altairEvalContainer'] ??= new \Altair\Container\Container();
                    \$container = \$GLOBALS['__altairEvalContainer'];
                    return \$id === null ? \$container : \$container->get(\$id);
                }
            }

            \$GLOBALS['__altairEvalContainer'] = \$__altairEvalContainer;

            \$__altairEvalStart     = hrtime(true);
            \$__altairEvalResult    = null;
            \$__altairEvalException = null;

            ob_start();
            try {
                \$__altairEvalResult = (static function (string \$file) {
                    return require \$file;
                })(\$__altairEvalSnippetFile);
            } catch (\Throwable \$__altairEvalThrowable) {
                \$__altairEvalException = \$__altairEvalThrowable;
            }
            \$__altairEvalStdout = (string) ob_get_clean();

            \$__altairEvalPayload = [
                'result' => \$__altairEvalException === null
                    ? \Altair\Eval\Encoder\ValueEncoder::encode(\$__altairEvalResult)
                    : null,
                'stdout' => \$__altairEvalStdout,
                'exception' => \$__altairEvalException === null
                    ? null
                    : \Altair\Eval\Encoder\ExceptionEncoder::encode(\$__altairEvalException),
                'memory_peak_bytes' => memory_get_peak_usage(true),
                'duration_ms' => (int) ((hrtime(true) - \$__altairEvalStart) / 1_000_000),
            ];

            file_put_contents(
                \$__altairEvalResultFile,
                json_encode(\$__altairEvalPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            );

            exit(\$__altairEvalException === null ? 0 : 1);
            PHP;
    }
}
