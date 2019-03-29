<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware;

use Altair\Middleware\Contracts\MiddlewareManagerInterface;
use Altair\Middleware\Contracts\MiddlewareRunnerInterface;
use Altair\Middleware\Contracts\PayloadInterface;

class MiddlewareManager implements MiddlewareManagerInterface
{
    /**
     * @var Runner
     */
    protected $runner;

    /**
     * Manager constructor.
     *
     * @param MiddlewareRunnerInterface $runner
     */
    public function __construct(MiddlewareRunnerInterface $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    public function __invoke(PayloadInterface $payload)
    {
        $runner = $this->runner;

        return $runner($payload);
    }
}
