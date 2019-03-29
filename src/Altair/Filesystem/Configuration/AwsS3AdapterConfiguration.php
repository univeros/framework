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
use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

class AwsS3AdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $config = [
                'credentials' => [
                    'key' => $this->env->get('FS_AWS_S3_KEY'),
                    'secret' => $this->env->get('FS_AWS_S3_SECRET'),
                ],
                'region' => $this->env->get('FS_AWS_S3_REGION'),
                'version' => $this->env->get('FS_AWS_S3_VERSION', 'latest')
            ];

            // this configuration class does not support `options`
            // simply create your own and add the options you require
            return new AwsS3Adapter(
                (new S3Client($config)),
                $this->env->get('FS_AWS_S3_BUCKET'),
                $this->env->get('FS_AWS_S3_PREFIX')
            );
        };

        $container
            ->delegate(AwsS3Adapter::class, $factory)
            ->alias(AdapterInterface::class, AwsS3Adapter::class);
    }
}
