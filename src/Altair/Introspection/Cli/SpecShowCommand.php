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
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair spec:show <path>` — the parsed, flattened view of one
 * spec file. Tolerant of spec files that don't yet pass scaffolder
 * validation (useful for debugging "why won't this scaffold?").
 */
#[Command(
    name: 'spec:show',
    description: 'Show the parsed contents of one YAML spec file.',
)]
final readonly class SpecShowCommand
{
    public function __construct(
        private SpecInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Argument(description: 'Spec path (relative to the spec root, or absolute).')]
        string $path,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            $table = $this->inspector->inspectOne($path);
        } catch (NotFoundException $e) {
            echo $e->getMessage(), "\n";

            return 1;
        } catch (IntrospectionException $e) {
            echo $e->getMessage(), "\n";

            return 2;
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
