<?php
namespace Altair\Configuration\Contracts;

use Altair\Container\Container;
use Altair\Container\Exception\InvalidArgumentException;

interface ConfigurationInterface
{
    /**
     * Applies a configuration set to a dependency injector.
     *
     * @param Container $container
     *
     * @throws InvalidArgumentException
     */
    public function apply(Container $container);
}
