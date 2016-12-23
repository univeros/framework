<?php
namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Http\Collection\RouteCollection;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

class FastRouteConfiguration implements ConfigurationInterface
{
    /**
     * @var RouteCollection
     */
    protected $routeCollection;

    /**
     * FastRouteConfiguration constructor.
     *
     * @param RouteCollection $routeCollection
     */
    public function __construct(RouteCollection $routeCollection)
    {
        $this->routeCollection = $routeCollection;
    }

    /**
     * @param Container $container
     */
    public function apply(Container $container)
    {
        $factory = function () {
            return \FastRoute\simpleDispatcher(
                function (RouteCollector $routeCollector) {
                    foreach ($this->routeCollection as $request => $action) {
                        list($method, $path) = explode(' ', $request, 2);
                        $routeCollector->addRoute($method, $path, $action);
                    }
                }
            );
        };
        $container->delegate(Dispatcher::class, $factory);
    }
}
