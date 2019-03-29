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
use League\Flysystem\Sftp\SftpAdapter;

class SftpAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $config = array_filter(
                [
                    'host' => $this->env->get('FS_SFTP_HOST'),
                    'hostFingerPrint' => $this->env->get('FS_SFTP_HOST_FINGERPRINT'),
                    'port' => $this->env->get('FS_SFTP_PORT'),
                    'username' => $this->env->get('FS_SFTP_USERNAME'),
                    'password' => $this->env->get('FS_SFTP_PASSWORD'),
                    'useAgent' => $this->env->get('FS_SFTP_USE_AGENT'),
                    'agent' => $this->env->get('FS_SFTP_AGENT'),
                    'timeout' => $this->env->get('FS_SFTP_TIMEOUT'),
                    'root' => $this->env->get('FS_SFTP_ROOT'),
                    'privateKey' => $this->env->get('FS_SFTP_PRIVATE_KEY'),
                    'permPrivate' => $this->env->get('FS_SFTP_PERM_PRIVATE'),
                    'permPublic' => $this->env->get('FS_SFTP_PERM_PUBLIC'),
                    'directoryPerm' => $this->env->get('FS_SFTP_DIRECTORY_PERM'),
                    'NetSftpConnection' => $this->env->get('FS_SFTP_NET_SFTP_CONNECTION')
                ]
            );

            return new SftpAdapter($config);
        };

        $container
            ->delegate(SftpAdapter::class, $factory)
            ->alias(AdapterInterface::class, SftpAdapter::class);
    }
}
