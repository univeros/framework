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
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair routes:show <path>` — every registration for one path
 * (a path can appear under multiple HTTP methods).
 */
#[Command(
    name: 'routes:show',
    description: 'Show every registration for one route path.',
)]
final readonly class RoutesShowCommand
{
    public function __construct(
        private RouteInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Argument(description: 'The path to look up (e.g. /users).')]
        string $path,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            $table = $this->inspector->inspectOne($path);
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
