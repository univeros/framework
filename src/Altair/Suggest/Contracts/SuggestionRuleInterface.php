<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Contracts;

use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Snapshot\Snapshot;

/**
 * One refactoring heuristic.
 *
 * A rule is a pure function of the {@see Snapshot}: it reads the structural
 * facts it cares about and yields zero or more {@see Suggestion}s. It must
 * not perform I/O, reflection, or instantiation — all of that happens once,
 * up front, in the {@see \Altair\Suggest\Snapshot\SnapshotFactory}. A rule
 * that lacks the data it needs (e.g. no specs were collected) returns `[]`
 * rather than guessing.
 */
interface SuggestionRuleInterface
{
    /**
     * Stable machine identifier, e.g. `dead_event`. Used by `--only` /
     * `--skip` and as the `rule` field on every suggestion it emits.
     */
    public function name(): string;

    /**
     * @return list<Suggestion>
     */
    public function analyse(Snapshot $snapshot): array;
}
