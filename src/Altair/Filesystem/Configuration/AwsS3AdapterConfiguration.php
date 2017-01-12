<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Filesystem\Contracts\FilesystemAdapterInterface;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v2\AwsS3Adapter;


class AwsS3AdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $config = array_filter(
            [
                'key' => $this->env->get('AWS_S3_KEY'),
                'secret' => $this->env->get('AWS_S3_SECRET'),
                'bucket' => $this->env->get('AWS_S3_BUCKET'),
                'region' => $this->env->get('AWS_S3_REGION'),
                'base_url' => $this->env->get('AWS_S3_BASE_URL'),
                'prefix' => $this->env->get('AWS_S3_PREFIX')
            ]
        );

        $factory = function () use ($config) {
            $clientConfig = array_filter(
                $config,
                function ($k) {
                    return in_array($k, ['key', 'secret', 'region', 'base_url']);
                }
            );

            return new AwsS3Adapter(
                S3Client::factory($clientConfig),
                $config['bucket'],
                $config['prefix']
            );
        };
        $container
            ->delegate(AwsS3Adapter::class, $factory)
            ->alias(FilesystemAdapterInterface::class, AwsS3Adapter::class);
    }
}
