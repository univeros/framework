<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use League\Flysystem\AdapterInterface;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;

class ZipArchiveAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            return new ZipArchiveAdapter(
                $this->env->get('FS_ZIP_ARCHIVE_PATH'),
                null,
                $this->env->get('FS_ZIP_ARCHIVE_PREFIX')
            );
        };

        $container
            ->delegate(ZipArchiveAdapter::class, $factory)
            ->alias(AdapterInterface::class, ZipArchiveAdapter::class);
    }
}
