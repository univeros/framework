<?php
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
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $adapterDefinition = $this->getAdapterDefinition();

        $container
            ->define(PostgreSqlPdoSessionAdapter::class, $adapterDefinition)
            ->alias(PdoSessionAdapterInterface::class, PostgreSqlPdoSessionAdapter::class)
            ->alias(SessionHandlerInterface::class, PdoSessionHandler::class);
    }
}
