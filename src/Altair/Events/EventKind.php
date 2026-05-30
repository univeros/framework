<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Exception\InvalidArgumentException;

/**
 * The catalogue of mutating operations the framework records.
 *
 * Keep these in sync with the table in issue #77. New kinds should be
 * added in alphabetical order to keep the enum diffable.
 */
enum EventKind: string
{
    case CsFix = 'cs_fix';
    case Eval = 'eval';
    case IndexBuild = 'index_build';
    case ManifestGenerate = 'manifest_generate';
    case ManualEdit = 'manual_edit';
    case Migration = 'migration';
    case OpenapiImport = 'openapi_import';
    case RectorRun = 'rector_run';
    case Replay = 'replay';
    case Rewind = 'rewind';
    case Scaffold = 'scaffold';
    case WorkerConsume = 'worker_consume';

    public static function fromString(string $value): self
    {
        $kind = self::tryFrom($value);
        if (!$kind instanceof \Altair\Events\EventKind) {
            throw new InvalidArgumentException(
                \sprintf("Unknown event kind '%s'.", $value),
            );
        }

        return $kind;
    }
}
