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
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class LocalAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $container
            ->delegate(LocalFilesystemAdapter::class, fn (): LocalFilesystemAdapter => new LocalFilesystemAdapter(
                $this->env->get('FS_LOCAL_PATH'),
                PortableVisibilityConverter::fromArray([]),
                $this->env->get('FS_LOCAL_LOCK', LOCK_EX),
                $this->env->get('FS_LOCAL_DISALLOW_LINKS', LocalFilesystemAdapter::DISALLOW_LINKS),
            ))
            ->alias(FilesystemAdapter::class, LocalFilesystemAdapter::class);
    }
}
