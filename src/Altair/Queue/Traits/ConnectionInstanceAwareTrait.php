<?php
namespace Altair\Queue\Traits;

trait ConnectionInstanceAwareTrait
{
    /**
     * @var $mixed
     */
    protected $instance;

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance?? $this->connect()->getInstance();
    }
}
