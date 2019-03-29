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
use League\Flysystem\Azure\AzureAdapter;
use MicrosoftAzure\Storage\Common\ServicesBuilder;

class AzureAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            return new AzureAdapter(
                ServicesBuilder::getInstance()->createBlobService(
                    sprintf(
                        'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
                        base64_encode($this->env->get('FS_AZURE_ACCOUNT_NAME', '')),
                        base64_encode($this->env->get('FS_AZURE_KEY', ''))
                    )
                ),
                $this->env->get('FS_AZURE_CONTAINER')
            );
        };
        $container
            ->delegate(AzureAdapter::class, $factory)
            ->alias(AdapterInterface::class, AzureAdapter::class);
    }
}
