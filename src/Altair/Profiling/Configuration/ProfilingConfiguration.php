<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Profiling\Sampler\BackendDetector;
use Altair\Profiling\Storage\FilesystemProfileStorage;
use Override;

/**
 * Wires a shared {@see FilesystemProfileStorage} (and a {@see BackendDetector})
 * into the Container. Optional: the `profile:*` CLI commands build their own
 * storage from the current working directory; this Configuration is for hosts
 * (and the MCP server) that want an explicit profiles directory.
 */
final readonly class ProfilingConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $profilesDirectory = null,
        private int $maxKept = 100,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $dir = $this->profilesDirectory ?? getcwd() . '/.altair/profiles';
        $max = $this->maxKept;

        $container
            ->factory(FilesystemProfileStorage::class, static fn(): FilesystemProfileStorage => new FilesystemProfileStorage($dir, $max))
            ->shared();

        $container->singleton(BackendDetector::class);
    }
}
