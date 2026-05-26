<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Writer;

use Altair\Scaffold\Emitter\EmittedFile;
use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Exception\ScaffoldException;

/**
 * Resolves emitted file paths against a project root and writes them to disk.
 *
 * Route entries are special-cased: they are appended to the existing routes
 * file (or a fresh one is created) rather than overwritten.
 */
class FileWriter
{
    public function __construct(private readonly string $projectRoot) {}

    public function write(EmittedFile $file, bool $force): WriteOutcome
    {
        $absolute = $this->projectRoot . DIRECTORY_SEPARATOR . $file->relativePath;

        if ($file->kind === EmittedFileKind::Route) {
            return $this->appendRoute($absolute, $file);
        }

        if (!$force && is_file($absolute)) {
            return new WriteOutcome($file->relativePath, WriteStatus::Skipped);
        }

        $this->ensureDirectory(\dirname($absolute));
        if (file_put_contents($absolute, $file->contents) === false) {
            throw new ScaffoldException(\sprintf("Failed to write '%s'.", $absolute));
        }

        return new WriteOutcome($file->relativePath, WriteStatus::Written);
    }

    private function appendRoute(string $absolute, EmittedFile $file): WriteOutcome
    {
        $this->ensureDirectory(\dirname($absolute));

        $existing = is_file($absolute) ? (string) file_get_contents($absolute) : '';
        if ($existing !== '' && str_contains($existing, trim($file->contents))) {
            return new WriteOutcome($file->relativePath, WriteStatus::Skipped);
        }

        if ($existing === '') {
            $existing = <<<'PHP'
                <?php

                declare(strict_types=1);

                return [
                ];

                PHP;
        }

        $patched = preg_replace(
            '/return\s*\[\s*\n/',
            "return [\n" . $file->contents . "\n",
            $existing,
            1,
        );

        if ($patched === null || $patched === $existing) {
            // Fallback: append the entry on its own line near the closing bracket.
            $patched = str_replace('];', $file->contents . "\n];", $existing);
        }

        if (file_put_contents($absolute, $patched) === false) {
            throw new ScaffoldException(\sprintf("Failed to write '%s'.", $absolute));
        }

        return new WriteOutcome($file->relativePath, WriteStatus::Modified);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new ScaffoldException(\sprintf("Failed to create directory '%s'.", $directory));
        }
    }
}
