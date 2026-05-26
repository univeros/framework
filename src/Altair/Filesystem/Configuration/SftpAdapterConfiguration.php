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
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class SftpAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[\Override]
    public function apply(Container $container): void
    {
        $container
            ->delegate(SftpAdapter::class, fn (): SftpAdapter => new SftpAdapter(
                new SftpConnectionProvider(
                    $this->env->get('FS_SFTP_HOST'),
                    $this->env->get('FS_SFTP_USERNAME'),
                    $this->env->get('FS_SFTP_PASSWORD'),
                    $this->env->get('FS_SFTP_PRIVATE_KEY'),
                    $this->env->get('FS_SFTP_PASSPHRASE'),
                    (int) $this->env->get('FS_SFTP_PORT', 22),
                    (bool) $this->env->get('FS_SFTP_USE_AGENT', false),
                    (int) $this->env->get('FS_SFTP_TIMEOUT', 10),
                    (int) $this->env->get('FS_SFTP_MAX_TRIES', 4),
                    $this->env->get('FS_SFTP_HOST_FINGERPRINT'),
                ),
                $this->env->get('FS_SFTP_ROOT', '/'),
                PortableVisibilityConverter::fromArray([]),
            ))
            ->alias(FilesystemAdapter::class, SftpAdapter::class);
    }
}
