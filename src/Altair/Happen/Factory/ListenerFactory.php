<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen\Factory;

use Altair\Happen\Contracts\ListenerInterface;
use Altair\Happen\Listener\CallbackListener;

class ListenerFactory
{
    public static function create(callable $callback): ListenerInterface
    {
        return new CallbackListener($callback);
    }
}
