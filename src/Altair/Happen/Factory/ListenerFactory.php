<?php
namespace Altair\Happen\Factory;

use Altair\Happen\Listener\CallbackListener;
use Altair\Happen\ListenerInterface;

class ListenerFactory
{
    /**
     *
     * @param callable $callback
     *
     * @return ListenerInterface
     */
    public static function create(callback $callback): ListenerInterface
    {
        return new CallbackListener($callback);
    }
}
