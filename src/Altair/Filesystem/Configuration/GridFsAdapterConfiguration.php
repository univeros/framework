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
use League\Flysystem\GridFS\GridFSAdapter;
use MongoClient;

class GridFsAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
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
