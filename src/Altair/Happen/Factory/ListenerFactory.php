<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
    public static function create(callable $callback): ListenerInterface
    {
        return new CallbackListener($callback);
    }
}
