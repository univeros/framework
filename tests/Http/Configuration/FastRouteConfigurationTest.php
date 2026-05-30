<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Configuration;

use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Http\Base\Action;
use Altair\Http\Collection\RouteCollection;
use Altair\Http\Configuration\FastRouteConfiguration;
use FastRoute\Dispatcher;
use Override;
use PHPUnit\Framework\TestCase;

class FastRouteConfigurationTest extends TestCase
{
    /** @var list<string> */
    private array $appliedKeys = [];

    private ?string $cacheFile = null;

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->appliedKeys as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        $this->appliedKeys = [];

        if ($this->cacheFile !== null && \file_exists($this->cacheFile)) {
            \unlink($this->cacheFile);
        }

        $this->cacheFile = null;

        parent::tearDown();
    }

    public function testDefaultsToSimpleDispatcherWhenEnvIsNotProvided(): void
    {
        $container = new Container();
        $routes = $this->routes();

        (new FastRouteConfiguration($routes))->apply($container);

        $dispatcher = $container->get(Dispatcher::class);

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $result = $dispatcher->dispatch('GET', '/users/42');
        $this->assertSame(Dispatcher::FOUND, $result[0]);
        $this->assertInstanceOf(Action::class, $result[1]);
        $this->assertSame(['id' => '42'], $result[2]);
    }

    public function testSimpleDispatcherFallbackWhenRouteCacheFileIsNotSet(): void
    {
        $container = new Container();

        (new FastRouteConfiguration($this->routes(), new Env()))->apply($container);

        $dispatcher = $container->get(Dispatcher::class);

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $result = $dispatcher->dispatch('GET', '/users/42');
        $this->assertSame(Dispatcher::FOUND, $result[0]);
    }

    public function testCachedDispatcherWritesCacheFileWhenRouteCacheFileIsSet(): void
    {
        $this->cacheFile = \sys_get_temp_dir() . '/altair-fastroute-' . \uniqid('', true) . '.php';
        $this->assertFileDoesNotExist($this->cacheFile);

        $this->setEnv(['ROUTE_CACHE_FILE' => $this->cacheFile]);

        $container = new Container();
        (new FastRouteConfiguration($this->routes(), new Env()))->apply($container);

        $dispatcher = $container->get(Dispatcher::class);

        $this->assertFileExists($this->cacheFile);
        $cached = require $this->cacheFile;
        $this->assertIsArray($cached);

        $result = $dispatcher->dispatch('GET', '/users/42');
        $this->assertSame(Dispatcher::FOUND, $result[0]);
    }

    public function testCachedDispatcherReadsExistingCacheFileWithoutRecompiling(): void
    {
        $this->cacheFile = \sys_get_temp_dir() . '/altair-fastroute-' . \uniqid('', true) . '.php';
        $this->setEnv(['ROUTE_CACHE_FILE' => $this->cacheFile]);

        // First boot: compiles + writes the cache file.
        $first = new Container();
        (new FastRouteConfiguration($this->routes(), new Env()))->apply($first);
        $first->get(Dispatcher::class);
        $this->assertFileExists($this->cacheFile);

        $cachedMtime = \filemtime($this->cacheFile);

        // Second boot with an empty collection — if the cache were ignored the route
        // would not be found. Reading the cache file proves the warm path is taken.
        $second = new Container();
        (new FastRouteConfiguration(new RouteCollection(), new Env()))->apply($second);
        $dispatcher = $second->get(Dispatcher::class);

        $result = $dispatcher->dispatch('GET', '/users/42');
        $this->assertSame(Dispatcher::FOUND, $result[0]);
        $this->assertSame($cachedMtime, \filemtime($this->cacheFile), 'cache file should not be rewritten on warm boot');
    }

    public function testRouteCacheDisabledForcesSimpleDispatcherEvenWhenCacheFileIsSet(): void
    {
        $this->cacheFile = \sys_get_temp_dir() . '/altair-fastroute-' . \uniqid('', true) . '.php';
        $this->setEnv([
            'ROUTE_CACHE_FILE' => $this->cacheFile,
            'ROUTE_CACHE_DISABLED' => '1',
        ]);

        $container = new Container();
        (new FastRouteConfiguration($this->routes(), new Env()))->apply($container);

        $dispatcher = $container->get(Dispatcher::class);

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $this->assertFileDoesNotExist(
            $this->cacheFile,
            'cache file must not be written when ROUTE_CACHE_DISABLED is truthy',
        );

        $result = $dispatcher->dispatch('GET', '/users/42');
        $this->assertSame(Dispatcher::FOUND, $result[0]);
    }

    public function testEmptyRouteCacheFileFallsBackToSimpleDispatcher(): void
    {
        $this->setEnv(['ROUTE_CACHE_FILE' => '']);

        $container = new Container();
        (new FastRouteConfiguration($this->routes(), new Env()))->apply($container);

        $dispatcher = $container->get(Dispatcher::class);

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $result = $dispatcher->dispatch('GET', '/users/42');
        $this->assertSame(Dispatcher::FOUND, $result[0]);
    }

    private function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->put('GET /users/{id:\d+}', new Action(\stdClass::class));

        return $routes;
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            \putenv($key . '=' . $value);
            $this->appliedKeys[] = $key;
        }
    }
}
