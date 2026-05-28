<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Configuration\Collection;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Exception\InvalidConfigurationException;
use Altair\Container\Container;
use Altair\Structure\Set;
use Override;

/**
 * @extends Set<class-string|ConfigurationInterface>
 */
class ConfigurationCollection extends Set implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        foreach ($this as $configuration) {
            if (!\is_object($configuration)) {
                $configuration = $container->make($configuration);
            }

            if ($configuration instanceof ConfigurationInterface) {
                $configuration->apply($container);
            } else {
                throw new InvalidConfigurationException(
                    \sprintf(
                        "Configuration class '%s' must implement '%s'",
                        \is_object($configuration) ? $configuration::class : $configuration,
                        ConfigurationInterface::class
                    )
                );
            }
        }
    }
}
