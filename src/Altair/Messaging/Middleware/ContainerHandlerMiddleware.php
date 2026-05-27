<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Middleware;

use Altair\Messaging\HandlerLocator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

/**
 * Framework-named entry point for handler dispatch. Extends Symfony's
 * HandleMessageMiddleware with no behavior changes — having our own
 * type gives us a stable slot to swap or wrap later (telemetry, retry
 * gates, etc.) without churning user code.
 */
final class ContainerHandlerMiddleware extends HandleMessageMiddleware
{
    public function __construct(
        HandlerLocator $locator,
        bool $allowNoHandlers = false,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($locator, $allowNoHandlers);

        if ($logger !== null) {
            $this->setLogger($logger);
        }
    }
}
