<?php
namespace Altair\Configuration\Contracts;

use Altair\Container\Container;

interface ConfigurationInterface
{
    /**
     * Applies a configuration set to a dependency injector.
     *
     * @param Container $container
     */
    public function apply(Container $container);
}
