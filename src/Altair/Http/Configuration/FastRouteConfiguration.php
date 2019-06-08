<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * @inheritDoc
     */
    public function apply(Container $container): void
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
