<?php
namespace Altair\Queue\Traits;

use Altair\Queue\Contracts\QueueConnectionInterface;

trait ConnectionInstanceAwareTrait
{
    /**
     * @var mixed
     */
    protected $instance;

    /**
     * @inheritdoc
     */
    public function getConnection(): QueueConnectionInterface
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
