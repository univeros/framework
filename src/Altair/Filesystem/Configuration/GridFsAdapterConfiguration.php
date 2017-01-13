<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use League\Flysystem\AdapterInterface;
use League\Flysystem\GridFS\GridFSAdapter;
use MongoClient;

class GridFsAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $gridFs = (new MongoClient($this->env->get('FS_GRID_FS_SERVER')))
                ->selectDB($this->env->get('FS_GRID_FS_DATABASE'))
                ->getGridFS($this->env->get('FS_GRID_FS_PREFIX', 'fs'));

            return new GridFSAdapter($gridFs);
        };

        $container
            ->delegate(GridFSAdapter::class, $factory)
            ->alias(AdapterInterface::class, GridFSAdapter::class);
    }
}
