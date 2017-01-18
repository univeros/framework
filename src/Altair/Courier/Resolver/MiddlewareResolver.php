<?php
namespace Altair\Courier\Resolver;

use Altair\Container\Container;
use Altair\Courier\Contracts\MiddlewareResolverInterface;

class MiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * MiddlewareResolver constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolve a class spec into an object, if it is not already instantiated.
     *
     * @param string|object $entry
     *
     * @return object
     */
    public function __invoke($entry)
    {
        if (is_object($entry)) {
            return $entry;
        }

        return $this->container->make($entry);
    }
}
