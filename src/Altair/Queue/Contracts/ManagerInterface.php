<?php
namespace Altair\Queue\Contracts;

interface ManagerInterface extends QueueInterface
{
    public function getAdapter(): AdapterInterface;
}
