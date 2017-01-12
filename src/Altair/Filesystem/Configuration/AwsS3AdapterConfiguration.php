<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Filesystem\Contracts\FilesystemAdapterInterface;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;


class AwsS3AdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {

        $factory = function (){
            $config = [
                'credentials' => [
                    'key' => $this->env->get('AWS_S3_KEY'),
                    'secret' => $this->env->get('AWS_S3_SECRET'),
                ],
                'region' => $this->env->get('AWS_S3_REGION'),
                'version' => $this->env->get('AWS_S3_VERSION', 'latest')
            ];

            return new AwsS3Adapter(
                (new S3Client($config)),
                $this->env->get('AWS_S3_BUCKET'),
                $this->env->get('AWS_S3_PREFIX')
            );
        };

        $container
            ->delegate(AwsS3Adapter::class, $factory)
            ->alias(FilesystemAdapterInterface::class, AwsS3Adapter::class);
    }
}
