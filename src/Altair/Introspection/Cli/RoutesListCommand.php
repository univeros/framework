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
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair routes:list` — every registered route, alpha-sorted by path.
 */
#[Command(
    name: 'routes:list',
    description: 'List every registered route with its method and action.',
)]
final readonly class RoutesListCommand
{
    public function __construct(
        private RouteInspector $inspector,
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
