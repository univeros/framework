<?php
namespace Altair\Session\Traits;

use Altair\Container\Definition;
use Altair\Session\Contracts\PdoSessionAdapterInterface;

trait PdoAdapterDefinitionAwareTrait
{
    protected function getAdapterDefinition(): Definition
    {
        return new Definition(
            [
                ':dsn' => $this->env->get('SESSION_PDO_DSN'),
                ':username' => $this->env->get('SESSION_PDO_USERNAME'),
                ':password' => $this->env->get('SESSION_PDO_PASSWORD'),
                ':table' => $this->env->get('SESSION_PDO_TABLE'),
                ':lockMode' => (int)$this->env->get(
                    'SESSION_LOCK_MODE',
                    PdoSessionAdapterInterface::LOCK_TRANSACTIONAL
                ),
                ':options' => [] // if you need to add X connection options, create your own ;)
            ]
        );
    }
}
