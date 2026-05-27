<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair listeners:show <event>` — listeners for one event in
 * priority order.
 */
#[Command(
    name: 'listeners:show',
    description: 'Show listeners for one event name in priority order.',
)]
final readonly class ListenersShowCommand
{
    public function __construct(
        private ListenerInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Argument(description: 'Event name to inspect (e.g. user.created).')]
        string $event,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            $table = $this->inspector->inspectOne($event);
        } catch (NotFoundException $notFoundException) {
            echo $notFoundException->getMessage(), "\n";

            return 1;
        }

        try {
            echo $this->renderers->get($format)->render($table);
        } catch (IntrospectionException $introspectionException) {
            echo $introspectionException->getMessage(), "\n";

            return 2;
        }

        return 0;
    }
}
