<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Snapshot;

/**
 * An immutable, structural snapshot of "what is wired into this project" —
 * the single input every {@see \Altair\Suggest\Contracts\SuggestionRuleInterface}
 * reads. Built once by the {@see SnapshotFactory}; rules never touch the
 * Container, the filesystem, or reflection directly.
 *
 * A section is empty when the project does not use that subsystem (no
 * routes, no event dispatcher, no specs). Rules treat empty sections as
 * "no signal" and stay silent rather than producing noise.
 */
final readonly class Snapshot
{
    /**
     * @param list<BindingNode> $bindings
     * @param list<RouteNode>   $routes
     * @param list<EventNode>   $events
     * @param list<string>      $middleware class names in the default pipeline, in dispatch order
     * @param list<SpecNode>    $specs
     */
    public function __construct(
        public array $bindings = [],
        public array $routes = [],
        public array $events = [],
        public array $middleware = [],
        public array $specs = [],
    ) {}
}
