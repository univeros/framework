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
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\AdapterInterface;

class FtpAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $factory = function () {
            $config = array_filter(
                [
                    'host' => $this->env->get('FS_FTP_HOST'),
                    'port' => $this->env->get('FS_FTP_PORT'),
                    'username' => $this->env->get('FS_FTP_USERNAME'),
                    'password' => $this->env->get('FS_FTP_PASSWORD'),
                    'ssl' => $this->env->get('FS_FTP_SSL'),
                    'timeout' => $this->env->get('FS_FTP_TIMEOUT'),
                    'root' => $this->env->get('FS_FTP_ROOT'),
                    'permPrivate' => $this->env->get('FS_FTP_PERM_PRIVATE'),
                    'permPublic' => $this->env->get('FS_FTP_PERM_PUBLIC'),
                    'passive' => $this->env->get('FS_FTP_PASSIVE'),
                    'transferMode' => $this->env->get('FS_FTP_TRANSFER_MODE'),
                    'systemType' => $this->env->get('FS_FTP_SYSTEM_TYPE'),
                    'ignorePassiveAddress' => $this->env->get('FS_FTP_IGNORE_PASSIVE_ADDRESS'),
                    'recurseManually' => $this->env->get('FS_FTP_RECURSE_MANUALLY')
                ]
            );

            return new Ftp($config);
        };

        $container
            ->delegate(Ftp::class, $factory)
            ->alias(AdapterInterface::class, Ftp::class);
    }
}
