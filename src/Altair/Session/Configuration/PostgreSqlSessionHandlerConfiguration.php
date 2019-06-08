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
use Altair\Session\Adapter\PostgreSqlPdoSessionAdapter;
use Altair\Session\Contracts\PdoSessionAdapterInterface;
use Altair\Session\Handler\PdoSessionHandler;
use Altair\Session\Traits\PdoAdapterDefinitionAwareTrait;
use SessionHandlerInterface;

class PostgreSqlSessionHandlerConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;
    use PdoAdapterDefinitionAwareTrait;

    /**
     * @inheritDoc
     */
    public function apply(Container $container): void
    {
        $adapterDefinition = $this->getAdapterDefinition();

        $container
            ->define(PostgreSqlPdoSessionAdapter::class, $adapterDefinition)
            ->alias(PdoSessionAdapterInterface::class, PostgreSqlPdoSessionAdapter::class)
            ->alias(SessionHandlerInterface::class, PdoSessionHandler::class);
    }
}
