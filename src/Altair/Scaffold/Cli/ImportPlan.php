<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Scaffold\Emitter\EmittedFile;

/**
 * Outcome of the planning phase of `openapi:import`: the spec files that
 * will be written, plus the operations that could not be mapped.
 *
 * Under `--skip-unmappable`, an operation whose schema the emitter cannot
 * express (e.g. a nested-object request body) is recorded in {@see $unmapped}
 * and a human-readable line is added to {@see $warnings} instead of aborting
 * the whole run. Without the flag the runner never builds a partial plan —
 * the first unmappable operation throws straight out of planning.
 */
final readonly class ImportPlan
{
    /**
     * @param list<EmittedFile>                              $files    Specs that will be written.
     * @param list<array{pointer: string, message: string}> $unmapped Operations skipped as unmappable.
     * @param list<string>                                   $warnings One line per skipped operation.
     */
    public function __construct(
        public array $files,
        public array $unmapped = [],
        public array $warnings = [],
    ) {}
}
