<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair listeners:list` — every event name with at least one
 * registered listener, plus the listener count.
 *
 * (Named `listeners:*` rather than `events:*` to avoid collision with
 * #77's mutation-event-log commands like `events:show <id>`.)
 */
#[Command(
    name: 'listeners:list',
    description: 'List every event name with at least one registered listener.',
)]
final readonly class ListenersListCommand
{
    public function __construct(
        private ListenerInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            echo $this->renderers->get($format)->render($this->inspector->inspectAll());
        } catch (IntrospectionException $introspectionException) {
            echo $introspectionException->getMessage(), "\n";

            return 2;
        }

        return 0;
    }
}
