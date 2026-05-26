<?php

declare(strict_types=1);

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
use Override;

class MiddlewareManager implements MiddlewareManagerInterface
{
    /**
     * Manager constructor.
     */
    public function __construct(
        /**
         * @var Runner
         */
        protected MiddlewareRunnerInterface $runner
    ) {}

    /**
     * @return PayloadInterface
     */
    #[Override]
    public function __invoke(PayloadInterface $payload)
    {
        $runner = $this->runner;

        return $runner($payload);
    }
}
