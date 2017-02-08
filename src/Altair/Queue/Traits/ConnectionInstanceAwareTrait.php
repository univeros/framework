<?php
namespace Altair\Queue\Traits;

use Altair\Queue\Contracts\ConnectionInterface;

trait ConnectionInstanceAwareTrait
{
    /**
     * @var mixed
     */
    protected $instance;

    /**
     * @inheritdoc
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->instance?? $this->connect()->getInstance();
    }

    /**
     * @inheritdoc
     */
    public function getInstance()
    {
        return $this->instance;
    }
}
