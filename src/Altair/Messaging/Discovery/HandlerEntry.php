<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Discovery;

/**
 * Immutable record of a single message -> handler mapping.
 */
final readonly class HandlerEntry
{
    /**
     * @param class-string $messageClass
     * @param class-string $handlerClass
     */
    public function __construct(
        public string $messageClass,
        public string $handlerClass,
        public string $method = '__invoke',
        public ?string $fromTransport = null,
        public int $priority = 0,
    ) {}
}
