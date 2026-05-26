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
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[\Override]
    public function apply(Container $container): void
    {
        $container
            ->delegate(DropboxAdapter::class, fn (): DropboxAdapter => new DropboxAdapter(
                new Client($this->env->get('FS_DROPBOX_ACCESS_TOKEN')),
                $this->env->get('FS_DROPBOX_PREFIX', ''),
            ))
            ->alias(FilesystemAdapter::class, DropboxAdapter::class);
    }
}
