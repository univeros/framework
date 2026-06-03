<?php

declare(strict_types=1);

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\ModuleConfiguration;

/*
 * Boot factory: build the container and apply the Configuration chain.
 * The container resolves its own type to itself, so collaborators that depend
 * on Container / ContainerInterface receive it without any explicit binding.
 * Returns the ready container.
 */
$container = new Container();

/** @var list<ConfigurationInterface> $configurations */
$configurations = require __DIR__ . '/configurations.php';
foreach ($configurations as $configuration) {
    $configuration->apply($container);
}

/*
 * Registered modules contribute their own services, routes, entities and
 * migrations. ModuleConfiguration tags them so the front controller and the
 * migrate commands can discover them.
 *
 * @var list<ModuleInterface> $modules
 */
$modules = require __DIR__ . '/modules.php';
(new ModuleConfiguration($modules))->apply($container);

return $container;
