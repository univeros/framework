<?php

declare(strict_types=1);

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

use function FastRoute\simpleDispatcher;

use Override;

class FastRouteConfiguration implements ConfigurationInterface
{
    /**
     * FastRouteConfiguration constructor.
     */
    public function __construct(protected RouteCollection $routeCollection) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        $factory = fn() => simpleDispatcher(
            function (RouteCollector $routeCollector): void {
                foreach ($this->routeCollection as $request => $action) {
                    [$method, $path] = explode(' ', $request, 2);
                    $routeCollector->addRoute($method, $path, $action);
                }
            }
        );
        $container->factory(Dispatcher::class, $factory);
    }
}
