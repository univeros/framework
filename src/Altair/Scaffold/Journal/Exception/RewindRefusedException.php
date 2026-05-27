<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Exception;

/**
 * Thrown when {@see RewindCommand} would clobber files the user has
 * hand-edited since the original scaffold. `--force` overrides.
 */
class RewindRefusedException extends JournalException
{
    /**
     * @param list<string> $unsafePaths File paths whose current sha256 doesn't match the journal entry.
     */
    public function __construct(
        string $message,
        public readonly array $unsafePaths,
    ) {
        parent::__construct($message);
    }
}
