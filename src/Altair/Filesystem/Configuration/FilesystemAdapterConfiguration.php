<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use Override;

class FilesystemAdapterConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        // The concrete FilesystemAdapter (Local/S3/Ftp/Sftp/Dropbox) is registered by a sibling configuration.
        // This configuration just wires it into a FilesystemOperator. Caching is no longer part of league/flysystem;
        // wrap with a caching decorator separately if needed.
        $container->delegate(
            FilesystemOperator::class,
            static fn(FilesystemAdapter $adapter): FilesystemOperator => new Filesystem($adapter),
        );
    }
}
