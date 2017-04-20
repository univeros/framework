<?php
namespace Altair\Middleware\Resolver;

use Altair\Container\Container;
use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;

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
     * @param $entry
     *
     * @return MiddlewareInterface
     */
    public function __invoke($entry): MiddlewareInterface
    {
        if (is_object($entry)) {
            return $entry;
        }

        return $this->container->make($entry);
    }
}
