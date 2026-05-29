<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Configuration;

use Altair\Cli\Application;
use Altair\Cli\Contracts\CommandLocatorInterface;
use Altair\Cli\Discovery\AttributeCommandDiscoverer;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Override;

/**
 * Wires the CLI sub-package into the Container.
 *
 * - Shares the AttributeCommandDiscoverer so it stays cheap to reuse.
 * - Aliases CommandLocatorInterface to the concrete discoverer.
 * - Delegates Application creation so it auto-discovers commands from
 *   the configured paths the first time it is resolved.
 */
class CliConfiguration implements ConfigurationInterface
{
    /**
     * @param list<string> $paths Directories to scan for #[Command]-attributed classes
     */
    public function __construct(
        private readonly array $paths = [],
        private readonly string $name = Application::DEFAULT_NAME,
        private readonly string $version = Application::DEFAULT_VERSION,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $paths = $this->paths;
        $name = $this->name;
        $version = $this->version;

        $container->alias(CommandLocatorInterface::class, AttributeCommandDiscoverer::class);
        $container->singleton(AttributeCommandDiscoverer::class);
        $container->factory(
            Application::class,
            static fn(
                AttributeCommandDiscoverer $discoverer,
            ): Application => (new Application($container, $name, $version))->discover($discoverer->scan($paths)),
        );
    }
}
