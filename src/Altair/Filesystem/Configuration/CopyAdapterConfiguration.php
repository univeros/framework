<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Barracuda\Copy\API;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Copy\CopyAdapter;

class CopyAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $api = new API(
                $this->env->get('FS_COPY_CONSUMER_KEY'),
                $this->env->get('FS_COPY_CONSUMER_SECRET'),
                $this->env->get('FS_COPY_ACCESS_TOKEN'),
                $this->env->get('FS_COPY_TOKEN_SECRET')
            );

            return new CopyAdapter(
                $api,
                $this->env->get('FS_COPY_PREFIX')
            );
        };

        $container
            ->delegate(CopyAdapter::class, $factory)
            ->alias(AdapterInterface::class, CopyAdapter::class);
    }
}
