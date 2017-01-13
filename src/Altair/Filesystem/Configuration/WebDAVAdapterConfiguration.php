<?php
namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use League\Flysystem\AdapterInterface;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Sabre\DAV\Client;

class WebDAVAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $config = array_filter(
                [
                    'baseUri' => $this->env->get('FS_WEBDAV_BASE_URI'),
                    'userName' => $this->env->get('FS_WEBDAV_USERNAME'),
                    'password' => $this->env->get('FS_WEBDAV_PASSWORD'),
                    'proxy' => $this->env->get('FS_WEBDAV_PROXY'),
                    'authType' => $this->env->get('FS_WEBDAV_AUTH_TYPE'),
                    'encoding' => $this->env->get('FS_WEBDAV_ENCODING'),
                ]
            );

            $client = new Client($config);

            return new WebDAVAdapter(
                $client,
                $this->env->get('FS_WEBDAV_PREFIX'),
                $this->env->get('FS_WEBDAV_USE_STREAM_COPY')
            );
        };

        $container
            ->delegate(WebDAVAdapter::class, $factory)
            ->alias(AdapterInterface::class, WebDAVAdapter::class);
    }
}
