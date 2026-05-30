<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library;

use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use RuntimeException;

/**
 * Builds the deterministic `index.json` published alongside the example
 * content. Output is sorted by id, contains no timestamps, and uses
 * pretty-printed JSON with a trailing newline — so the file diffs cleanly in
 * code review and CI can drift-gate it.
 */
final readonly class IndexBuilder
{
    public function __construct(
        private ExampleRepositoryInterface $repository,
    ) {}

    /**
     * Render the index as a JSON string. Pure function over the repository
     * contents — no I/O, no side effects.
     */
    public function build(): string
    {
        $entries = array_map(
            static fn(Example $example): array => $example->toIndexEntry(),
            $this->repository->findAll(),
        );

        $payload = [
            'version' => 1,
            'count' => \count($entries),
            'examples' => $entries,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode examples index as JSON.');
        }

        return $json . "\n";
    }

    /**
     * Write {@see build()} to a file atomically (write-and-rename).
     */
    public function writeTo(string $path): void
    {
        $contents = $this->build();
        $directory = \dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new RuntimeException(\sprintf('Unable to create index directory "%s".', $directory));
        }

        $temp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($temp, $contents) === false) {
            throw new RuntimeException(\sprintf('Unable to write examples index to "%s".', $temp));
        }

        if (!rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException(\sprintf('Unable to atomically replace examples index "%s".', $path));
        }
    }
}
