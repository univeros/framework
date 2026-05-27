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
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair middleware:list` — middleware pipeline in dispatch order.
 *
 * The `--pipeline` flag is accepted for forward-compatibility but
 * currently inert: the framework wires a single default pipeline, and
 * named-pipeline support belongs in a host-app extension. The inspector
 * surface is already pipeline-aware, so swapping registries is enough.
 */
#[Command(
    name: 'middleware:list',
    description: 'List the PSR-15 middleware pipeline in dispatch order.',
)]
final readonly class MiddlewareListCommand
{
    public function __construct(
        private PipelineInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Option(description: 'Pipeline name (default pipeline if omitted).')]
        ?string $pipeline = null,
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
