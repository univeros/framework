<?php
namespace Altair\Http\Resolver;

use Altair\Container\Container;
use Relay\ResolverInterface;

class ContainerResolver implements ResolverInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * ContainerResolver constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function __invoke($entry)
    {
        return is_object($entry) ? $entry : $this->container->make($entry);
    }
}
