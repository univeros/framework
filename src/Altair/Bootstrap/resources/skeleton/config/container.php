<?php

declare(strict_types=1);

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;

/*
 * Boot factory: build the container, bind it to itself (so collaborators can
 * receive it), and apply the Configuration chain. Returns the ready container.
 */
$container = new Container();
$container->share($container);

/** @var list<ConfigurationInterface> $configurations */
$configurations = require __DIR__ . '/configurations.php';
foreach ($configurations as $configuration) {
    $configuration->apply($container);
}

return $container;
