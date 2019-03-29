<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen\Listener;

use Altair\Happen\EventInterface;
use Altair\Happen\ListenerInterface;

class CallbackListener implements ListenerInterface
{
    /**
     * @var callable
     */
    protected $callback;

    /**
     * CallbackListener constructor.
     *
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param EventInterface $event
     */
    public function __invoke(EventInterface $event)
    {
        call_user_func($this->callback, $event);
    }
}
