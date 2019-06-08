<?php declare(strict_types=1);

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
use League\Flysystem\AdapterInterface;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $factory = function () {
            $client = new Client($this->env->get('FS_DROPBOX_ACCESS_TOKEN'));

            return new DropboxAdapter($client, $this->env->get('FS_DROPBOX_PREFIX', ''));
        };

        $container
            ->delegate(DropboxAdapter::class, $factory)
            ->alias(AdapterInterface::class, DropboxAdapter::class);
    }
}
