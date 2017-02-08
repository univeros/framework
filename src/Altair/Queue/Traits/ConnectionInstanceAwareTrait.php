<?php
namespace Altair\Queue\Traits;

use Altair\Queue\Contracts\ConnectionInterface;

trait ConnectionInstanceAwareTrait
{
    /**
     * @var ConnectionInterface
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
    public function getInstance(): ConnectionInterface
    {
        return $this->instance;
    }
}
