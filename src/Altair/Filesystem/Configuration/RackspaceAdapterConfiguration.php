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
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Rackspace\RackspaceAdapter;
use OpenCloud\Rackspace;

class RackspaceAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $factory = function () {
            $endpoint = $this->env->get('FS_RACKSPACE_ENDPOINT', Rackspace::US_IDENTITY_ENDPOINT);
            $username = $this->env->get('FS_RACKSPACE_USERNAME');
            $apiKey = $this->env->get('FS_RACKSPACE_API_KEY');
            $region = $this->env->get('FS_RACKSPACE_REGION');
            $container = $this->env->get('FS_RACKSPACE_CONTAINER');
            $prefix = $this->env->get('FS_RACKSPACE_PREFIX');

            $rackSpace = new Rackspace($endpoint, ['username' => $username, 'apiKey' => $apiKey]);

            return new RackspaceAdapter(
                $rackSpace->objectStoreService('cloudFiles', $region)->getContainer($container),
                $prefix
            );
        };

        $container
            ->delegate(Local::class, $factory)
            ->alias(AdapterInterface::class, Local::class);
    }
}
