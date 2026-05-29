<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Repl;

use Altair\Container\Container;
use Psy\Configuration;

/**
 * Builds a PsySH {@see Configuration} from a {@see ReplContext} and the startup
 * banner. Non-blocking and side-effect-light, so it is fully unit-testable —
 * the only interactive part (running the shell) lives in {@see PsyShellRepl}.
 */
final readonly class PsyConfigurationFactory
{
    public function create(ReplContext $context, string $startupMessage): Configuration
    {
        $configuration = new Configuration();

        if ($context->historyFile !== null && $context->historyFile !== '') {
            $configuration->setHistoryFile($context->historyFile);
        }

        if ($context->historySize > 0) {
            $configuration->setHistorySize($context->historySize);
        }

        if ($startupMessage !== '') {
            $configuration->setStartupMessage($startupMessage);
        }

        // A bundled dev tool should never phone home for version checks.
        $configuration->setUpdateCheck('never');
        $configuration->addCasters([Container::class => ContainerCaster::cast(...)]);

        return $configuration;
    }
}
