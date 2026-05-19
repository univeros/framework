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
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;

class AwsS3AdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $container
            ->delegate(AwsS3V3Adapter::class, fn (): AwsS3V3Adapter => new AwsS3V3Adapter(
                new S3Client([
                    'credentials' => [
                        'key' => $this->env->get('FS_AWS_S3_KEY'),
                        'secret' => $this->env->get('FS_AWS_S3_SECRET'),
                    ],
                    'region' => $this->env->get('FS_AWS_S3_REGION'),
                    'version' => $this->env->get('FS_AWS_S3_VERSION', 'latest'),
                ]),
                $this->env->get('FS_AWS_S3_BUCKET'),
                $this->env->get('FS_AWS_S3_PREFIX', ''),
            ))
            ->alias(FilesystemAdapter::class, AwsS3V3Adapter::class);
    }
}
