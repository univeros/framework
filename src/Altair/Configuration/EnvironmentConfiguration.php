<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Exception\InvalidArgumentException;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Dotenv\Dotenv;
use Override;

class EnvironmentConfiguration implements ConfigurationInterface
{
    private readonly string $directory;

    private readonly string $fileName;

    /**
     * @param string $filePath  Full path to the env file (e.g. /app/.env)
     * @param bool   $immutable When true, existing environment variables are not overridden
     */
    public function __construct(
        string $filePath,
        private readonly bool $immutable = true,
    ) {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException(\sprintf("Invalid environment file path: '%s'", $filePath));
        }

        $this->directory = \dirname($filePath);
        $this->fileName = basename($filePath);
    }

    #[Override]
    public function apply(Container $container): void
    {
        $container
            ->share(Env::class)
            ->delegate(
                Dotenv::class,
                fn(): Dotenv => $this->immutable
                    ? Dotenv::createImmutable($this->directory, $this->fileName)
                    : Dotenv::createMutable($this->directory, $this->fileName),
            )
            ->prepare(
                Env::class,
                static function (Env $env, Container $container): void {
                    $container->make(Dotenv::class)->load();
                },
            );
    }
}
