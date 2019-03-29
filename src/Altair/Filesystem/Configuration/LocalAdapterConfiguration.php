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

class LocalAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            return new Local(
                $this->env->get('FS_LOCAL_PATH'),
                $this->env->get('FS_LOCAL_LOCK', LOCK_EX),
                $this->env->get('FS_LOCAL_DISALLOW_LINKS', Local::DISALLOW_LINKS)
            );
        };

        $container
            ->delegate(Local::class, $factory)
            ->alias(AdapterInterface::class, Local::class);
    }
}
