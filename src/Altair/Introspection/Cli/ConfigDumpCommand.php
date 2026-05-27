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
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair config:dump` — merged environment + Container parameter
 * view. Secrets are masked by default; pass `--no-secrets=false` to
 * see raw values (useful only inside trusted dev shells).
 */
#[Command(
    name: 'config:dump',
    description: 'Dump merged env + container parameters; masks secret-named keys by default.',
)]
final readonly class ConfigDumpCommand
{
    public function __construct(
        private ConfigInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Option(description: 'Mask keys matching known secret patterns (default true).', name: 'no-secrets')]
        bool $noSecrets = true,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            echo $this->renderers->get($format)->render($this->inspector->dump(maskSecrets: $noSecrets));
        } catch (IntrospectionException $introspectionException) {
            echo $introspectionException->getMessage(), "\n";

            return 2;
        }

        return 0;
    }
}
