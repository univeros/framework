<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Session\Adapter\MySqlPdoSessionAdapter;
use Altair\Session\Contracts\PdoSessionAdapterInterface;
use Altair\Session\Handler\PdoSessionHandler;
use Altair\Session\Traits\PdoAdapterDefinitionAwareTrait;
use SessionHandlerInterface;

class MySqlSessionHandlerConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;
    use PdoAdapterDefinitionAwareTrait;

    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $adapterDefinition = $this->getAdapterDefinition();

        $container
            ->define(MySqlPdoSessionAdapter::class, $adapterDefinition)
            ->alias(PdoSessionAdapterInterface::class, MySqlPdoSessionAdapter::class)
            ->alias(SessionHandlerInterface::class, PdoSessionHandler::class);
    }
}
