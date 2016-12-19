<?php
namespace Altair\Configuration\Collection;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Exception\InvalidConfigurationException;
use Altair\Container\Container;
use Altair\Structure\Set;

class ConfigurationCollection extends Set implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        foreach ($this as $configuration) {
            if (!is_object($configuration)) {
                $configuration = $container->make($configuration);
            }
            if ($configuration instanceof ConfigurationInterface) {
                $configuration->apply($container);
            } else {
                throw new InvalidConfigurationException(
                    sprintf(
                        "Configuration class '%s' must implement '%s'",
                        get_class($configuration),
                        ConfigurationInterface::class
                    )
                );
            }
        }
    }
}
