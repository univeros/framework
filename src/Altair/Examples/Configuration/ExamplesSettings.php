<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Configuration;

use Altair\Configuration\Support\Env;

/**
 * Resolved paths for the examples library.
 *
 * | Env var                       | Default          | Meaning                                                                          |
 * |-------------------------------|------------------|----------------------------------------------------------------------------------|
 * | `ALTAIR_EXAMPLES_DIR`         | `.altair`        | Base directory (relative to project root).                                       |
 * | `ALTAIR_EXAMPLES_LIBRARY_DIR` | `examples`       | Library subdirectory inside the base directory.                                  |
 * | `ALTAIR_EXAMPLES_INDEX_FILE`  | `index.json`     | Index filename inside the library directory.                                     |
 */
final readonly class ExamplesSettings
{
    public function __construct(
        public string $projectRoot,
        public string $baseDirectory,
        public string $libraryDirectory,
        public string $indexFileName,
    ) {}

    public static function fromEnv(Env $env, ?string $projectRoot = null): self
    {
        $root = $projectRoot ?? self::guessProjectRoot();

        return new self(
            projectRoot: $root,
            baseDirectory: (string) $env->get('ALTAIR_EXAMPLES_DIR', '.altair'),
            libraryDirectory: (string) $env->get('ALTAIR_EXAMPLES_LIBRARY_DIR', 'examples'),
            indexFileName: (string) $env->get('ALTAIR_EXAMPLES_INDEX_FILE', 'index.json'),
        );
    }

    public function libraryPath(): string
    {
        return $this->joinPath($this->projectRoot, $this->baseDirectory, $this->libraryDirectory);
    }

    public function indexPath(): string
    {
        return $this->joinPath($this->libraryPath(), $this->indexFileName);
    }

    private function joinPath(string ...$parts): string
    {
        $normalised = array_map(static fn(string $p): string => rtrim($p, DIRECTORY_SEPARATOR), $parts);

        return implode(DIRECTORY_SEPARATOR, $normalised);
    }

    private static function guessProjectRoot(): string
    {
        $cwd = getcwd();

        return $cwd === false ? '.' : $cwd;
    }
}
