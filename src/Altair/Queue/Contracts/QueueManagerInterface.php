<?php
namespace Altair\Queue\Contracts;

interface QueueManagerInterface extends QueueInterface
{
    public function getAdapter(): QueueAdapterInterface;
}
