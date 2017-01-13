<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Dropbox\Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Dropbox\DropboxAdapter;

class DropboxAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $client = new Client(
                $this->env->get('FS_DROPBOX_ACCESS_TOKEN'),
                $this->env->get('FS_DROPBOX_CLIENT_IDENTIFIER'),
                $this->env->get('FS_DROPBOX_USER_LOCALE')
            );

            return new DropboxAdapter($client, $this->env->get('FS_DROPBOX_PREFIX'));
        };

        $container
            ->delegate(DropboxAdapter::class, $factory)
            ->alias(AdapterInterface::class, DropboxAdapter::class);
    }
}
