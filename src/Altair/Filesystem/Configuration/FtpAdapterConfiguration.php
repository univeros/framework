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
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\FilesystemAdapter;

class FtpAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $container
            ->delegate(FtpAdapter::class, fn (): FtpAdapter => new FtpAdapter(
                FtpConnectionOptions::fromArray(array_filter([
                    'host' => $this->env->get('FS_FTP_HOST'),
                    'root' => $this->env->get('FS_FTP_ROOT', '/'),
                    'username' => $this->env->get('FS_FTP_USERNAME'),
                    'password' => $this->env->get('FS_FTP_PASSWORD'),
                    'port' => $this->env->get('FS_FTP_PORT', 21),
                    'ssl' => $this->env->get('FS_FTP_SSL', false),
                    'timeout' => $this->env->get('FS_FTP_TIMEOUT', 90),
                    'passive' => $this->env->get('FS_FTP_PASSIVE', true),
                    'transferMode' => $this->env->get('FS_FTP_TRANSFER_MODE', FTP_BINARY),
                    'systemType' => $this->env->get('FS_FTP_SYSTEM_TYPE'),
                    'ignorePassiveAddress' => $this->env->get('FS_FTP_IGNORE_PASSIVE_ADDRESS'),
                    'recurseManually' => $this->env->get('FS_FTP_RECURSE_MANUALLY', false),
                ], static fn (mixed $v): bool => $v !== null)),
            ))
            ->alias(FilesystemAdapter::class, FtpAdapter::class);
    }
}
