<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Middleware;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Exception;

class CommandLockerMiddleware implements CommandMiddlewareInterface
{
    /**
     * @var array
     */
    protected $queue = [];
    /**
     * @var bool
     */
    protected $running = false;

    /**
     * @inheritDoc
     */
    public function handle(CommandMessageInterface $message, callable $next): void
    {
        // ensure we always execute one and only one command at a time
        $this->queue[] = $message;
        if (!$this->running) {
            $this->running = true;
            while ($message = array_shift($this->queue)) {
                try {
                    $next($message);
                } catch (Exception $e) {
                    $this->running = false;
                    throw $e;
                }
            }
            $this->running = false;
        }
    }
}
