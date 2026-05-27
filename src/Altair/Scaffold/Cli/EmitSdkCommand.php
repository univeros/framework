<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Scaffold\Sdk\EmitterRegistry;
use Altair\Scaffold\Sdk\Exception\SdkException;
use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OpenApiParser;

use const DIRECTORY_SEPARATOR;

use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * `bin/altair spec:emit-sdk <language>` — generate a typed client SDK
 * from the merged OpenAPI 3.1 document.
 *
 * ```bash
 * bin/altair spec:emit-sdk typescript > sdk.ts
 * bin/altair spec:emit-sdk typescript --out=clients/ts --multi-file
 * bin/altair spec:emit-sdk python --out=clients/python
 * bin/altair spec:emit-sdk --list
 * bin/altair spec:emit-sdk typescript --check        # exit 1 on drift
 * ```
 *
 * The OpenAPI document is read from `--openapi=<path>` or, by default,
 * produced on the fly by merging `docs/openapi/*.yaml` fragments (the
 * same merge `spec:emit-openapi` performs).
 */
#[Command(
    name: 'spec:emit-sdk',
    description: 'Generate a typed client SDK (TypeScript / Python) from the OpenAPI document.',
)]
final readonly class EmitSdkCommand
{
    public function __construct(
        private PathResolver $paths = new PathResolver(),
        private OpenApiParser $parser = new OpenApiParser(),
    ) {}

    public function __invoke(
        #[Argument(description: 'Target language: typescript or python.')]
        ?string $language = null,
        #[Option(description: 'List available SDK languages and exit.')]
        bool $list = false,
        #[Option(description: 'Path to a merged OpenAPI document. Defaults to merging docs/openapi/*.yaml.')]
        ?string $openapi = null,
        #[Option(description: 'Write output to this directory (multi-file) or file. Omit to write to stdout.', name: 'out')]
        ?string $out = null,
        #[Option(description: 'Emit one file per concern instead of a single bundle.', name: 'multi-file')]
        bool $multiFile = false,
        #[Option(description: 'Compare regenerated output against files on disk; exit 1 on drift.')]
        bool $check = false,
        #[Option(description: 'Override the project root.')]
        ?string $root = null,
    ): int {
        $registry = EmitterRegistry::default();

        if ($list || $language === null) {
            echo "Available SDK languages:\n";
            foreach ($registry->available() as $lang) {
                echo '  - ' . $lang . "\n";
            }

            return 0;
        }

        if (!$registry->has($language)) {
            echo \sprintf("Unknown language '%s'. Available: %s.\n", $language, implode(', ', $registry->available()));

            return 2;
        }

        try {
            $document = $this->loadDocument($root, $openapi);
        } catch (Throwable $throwable) {
            echo 'Could not load OpenAPI document: ' . $throwable->getMessage() . "\n";

            return 2;
        }

        $emitter = $registry->get($language);

        try {
            $emitted = $emitter->emit($document, $multiFile);
        } catch (SdkException $sdkException) {
            echo 'SDK emission failed: ' . $sdkException->getMessage() . "\n";

            return 2;
        }

        if ($check) {
            return $this->runCheck($emitted->files, $out, $emitter->defaultFileName());
        }

        if ($out === null) {
            echo $emitted->single();

            return 0;
        }

        return $this->writeFiles($emitted->files, $out, $multiFile, $emitter->defaultFileName());
    }

    /**
     * @param array<string, string> $files
     */
    private function writeFiles(array $files, string $out, bool $multiFile, string $defaultFileName): int
    {
        if (!$multiFile && \count($files) === 1) {
            // Single-file mode: `$out` is the target file path (or a dir).
            $target = is_dir($out) ? rtrim($out, '/\\') . DIRECTORY_SEPARATOR . $defaultFileName : $out;
            $this->ensureDir(\dirname($target));
            file_put_contents($target, reset($files));
            echo 'Wrote ' . $target . "\n";

            return 0;
        }

        foreach ($files as $relative => $contents) {
            $target = rtrim($out, '/\\') . DIRECTORY_SEPARATOR . $relative;
            $this->ensureDir(\dirname($target));
            file_put_contents($target, $contents);
            echo 'Wrote ' . $target . "\n";
        }

        return 0;
    }

    /**
     * @param array<string, string> $files
     */
    private function runCheck(array $files, ?string $out, string $defaultFileName): int
    {
        $drift = [];
        foreach ($files as $relative => $contents) {
            $target = $this->checkTarget($out, $relative, $defaultFileName, \count($files));
            if ($target === null || !is_file($target)) {
                $drift[] = $relative . ' (missing on disk)';
                continue;
            }

            if ((string) file_get_contents($target) !== $contents) {
                $drift[] = $relative . ' (differs)';
            }
        }

        if ($drift === []) {
            echo "SDK is up to date.\n";

            return 0;
        }

        echo "SDK drift detected:\n";
        foreach ($drift as $item) {
            echo '  - ' . $item . "\n";
        }

        return 1;
    }

    private function checkTarget(?string $out, string $relative, string $defaultFileName, int $fileCount): ?string
    {
        if ($out === null) {
            return null;
        }

        if ($fileCount === 1) {
            return is_dir($out) ? rtrim($out, '/\\') . DIRECTORY_SEPARATOR . $defaultFileName : $out;
        }

        return rtrim($out, '/\\') . DIRECTORY_SEPARATOR . $relative;
    }

    private function loadDocument(?string $root, ?string $openapi): OpenApiDocument
    {
        if ($openapi !== null) {
            if (!is_file($openapi)) {
                throw new SdkException(\sprintf("OpenAPI file '%s' not found.", $openapi));
            }

            return $this->parser->parseYaml((string) file_get_contents($openapi));
        }

        $projectRoot = $this->paths->resolveProjectRoot($root);
        $fragmentsDir = $projectRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'openapi';
        if (!is_dir($fragmentsDir)) {
            throw new SdkException(\sprintf("No OpenAPI document given and fragments dir '%s' does not exist.", $fragmentsDir));
        }

        return $this->parser->parseYaml($this->mergeFragments($fragmentsDir));
    }

    private function mergeFragments(string $directory): string
    {
        $merged = ['openapi' => '3.1.0', 'info' => ['title' => 'API', 'version' => '0.0.0'], 'paths' => [], 'components' => ['schemas' => []]];

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.{yaml,yml}', GLOB_BRACE) ?: [] as $file) {
            $fragment = Yaml::parseFile($file);
            if (!\is_array($fragment)) {
                continue;
            }

            if (\is_array($fragment['paths'] ?? null)) {
                $merged['paths'] = array_merge($merged['paths'], $fragment['paths']);
            }

            if (\is_array($fragment['components']['schemas'] ?? null)) {
                $merged['components']['schemas'] = array_merge($merged['components']['schemas'], $fragment['components']['schemas']);
            }
        }

        return Yaml::dump($merged, 8, 2);
    }

    private function ensureDir(string $dir): void
    {
        if ($dir !== '' && !is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new SdkException(\sprintf("Cannot create directory '%s'.", $dir));
        }
    }
}
