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
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair spec:list` — every YAML spec under the configured spec
 * root, summarised (path / method / route).
 */
#[Command(
    name: 'spec:list',
    description: 'List every YAML spec file with its endpoint method and path.',
)]
final readonly class SpecListCommand
{
    public function __construct(
        private SpecInspector $inspector,
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
