<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk;

/**
 * The output of an emitter: one or more files keyed by relative path.
 *
 * Single-file emission (the default) produces one entry; `--multi-file`
 * produces several. The CLI command writes each entry to disk (or, for
 * the single-file case, to stdout).
 */
final readonly class EmittedSdk
{
    /**
     * @param array<string, string> $files Relative path → file contents.
     */
    public function __construct(
        public array $files,
    ) {}

    /**
     * Convenience accessor for the single-file case.
     */
    public function single(): string
    {
        return implode("\n", $this->files);
    }

    public function isMultiFile(): bool
    {
        return \count($this->files) > 1;
    }
}
