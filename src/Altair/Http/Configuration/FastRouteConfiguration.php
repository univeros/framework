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
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Http\Collection\RouteCollection;

use function FastRoute\cachedDispatcher;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

use const FILTER_VALIDATE_BOOLEAN;

use Override;

class FastRouteConfiguration implements ConfigurationInterface
{
    /**
     * Environment key opting the dispatcher into FastRoute's file-based cache.
     * When unset or empty, the dispatcher falls back to {@see simpleDispatcher}.
     */
    public const string ENV_ROUTE_CACHE_FILE = 'ROUTE_CACHE_FILE';

    /**
     * Environment kill switch — when truthy, {@see simpleDispatcher} is used
     * even if {@see self::ENV_ROUTE_CACHE_FILE} is set. Useful for dev hosts
     * that always export the cache path but want recompiles on every request.
     */
    public const string ENV_ROUTE_CACHE_DISABLED = 'ROUTE_CACHE_DISABLED';

    public function __construct(
        protected RouteCollection $routeCollection,
        protected ?Env $env = null,
    ) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        $cacheFile = $this->resolveCacheFile();

        $definition = function (RouteCollector $routeCollector): void {
            foreach ($this->routeCollection as $request => $action) {
                [$method, $path] = explode(' ', $request, 2);
                $routeCollector->addRoute($method, $path, $action);
            }
        };

        $factory = $cacheFile === null
            ? fn(): Dispatcher => simpleDispatcher($definition)
            : fn(): Dispatcher => cachedDispatcher($definition, ['cacheFile' => $cacheFile]);

        $container->factory(Dispatcher::class, $factory);
    }

    /**
     * Returns the configured route-cache file path, or null when the dispatcher
     * should keep using {@see simpleDispatcher} (cache disabled or unconfigured).
     */
    private function resolveCacheFile(): ?string
    {
        if (!$this->env instanceof Env) {
            return null;
        }

        $disabled = filter_var(
            $this->env->get(self::ENV_ROUTE_CACHE_DISABLED, false),
            FILTER_VALIDATE_BOOLEAN,
        );
        if ($disabled) {
            return null;
        }

        $cacheFile = $this->env->get(self::ENV_ROUTE_CACHE_FILE);
        if (!\is_string($cacheFile) || $cacheFile === '') {
            return null;
        }

        return $cacheFile;
    }
}
