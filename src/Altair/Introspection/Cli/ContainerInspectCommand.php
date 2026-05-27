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
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair container:inspect [id]` — show the Container's binding
 * inventory, or zoom into one binding's detail.
 */
#[Command(
    name: 'container:inspect',
    description: 'Inspect Container bindings (aliases, shares, delegates, parameters, prepares).',
)]
final readonly class ContainerInspectCommand
{
    public function __construct(
        private ContainerInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Argument(description: 'Optional binding id (alias / class / parameter) to drill into.')]
        ?string $id = null,
        #[Option(description: 'Only show singleton (shared) bindings.')]
        bool $shared = false,
        #[Option(description: 'Case-insensitive substring filter on binding id.')]
        ?string $filter = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Only show services the Container has actually instantiated (realised view).')]
        bool $realized = false,
    ): int {
        try {
            $table = match (true) {
                $id !== null => $this->inspector->inspectOne($id),
                $realized => $this->inspector->inspectRealized(filter: $filter),
                default => $this->inspector->inspectAll(sharedOnly: $shared, filter: $filter),
            };
        } catch (NotFoundException $notFoundException) {
            echo $notFoundException->getMessage(), "\n";

            return 1;
        }

        // Human mode gets an explicit "nothing realised yet" line; JSON mode
        // already conveys it as an empty `rows` array, which agents parse.
        if ($realized && $id === null && $table->isEmpty() && $format !== 'json') {
            echo "No services realised yet.\n";

            return 0;
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
