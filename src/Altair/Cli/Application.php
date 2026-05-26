<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli;

use Altair\Container\Container;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Thin Symfony Console Application subclass that resolves user-authored
 * command classes through the framework's Container.
 */
class Application extends SymfonyApplication
{
    public const string DEFAULT_NAME = 'Altair';

    public const string DEFAULT_VERSION = '2.x-dev';

    public function __construct(
        private readonly Container $container,
        string $name = self::DEFAULT_NAME,
        string $version = self::DEFAULT_VERSION,
    ) {
        parent::__construct($name, $version);
    }

    /**
     * Register the given user command classes as Symfony Console commands.
     *
     * @param iterable<class-string> $commandClasses
     */
    public function discover(iterable $commandClasses): self
    {
        foreach ($commandClasses as $class) {
            $this->add(new AltairCommand($class, $this->container));
        }

        return $this;
    }
}
