<?php
namespace Altair\Queue\Contracts;

interface ConnectionInterface
{
    /**
     * @return ConnectionInterface
     */
    public function connect(): ConnectionInterface;

    /**
     * @return mixed
     */
    public function getInstance();
}
