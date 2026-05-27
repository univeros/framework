<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Attribute;

use Attribute;

/**
 * Declares that the decorated class handles dispatched messages of the
 * given type. The discoverer reads this attribute at boot time, no
 * manual registration required.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsHandler
{
    /**
     * @param class-string $messageClass FQCN of the message this handler accepts
     * @param string|null  $fromTransport Restrict handler to messages received from this transport
     * @param int          $priority      Higher runs first when multiple handlers match
     * @param string|null  $method        Method to call; defaults to __invoke
     */
    public function __construct(
        public string $messageClass,
        public ?string $fromTransport = null,
        public int $priority = 0,
        public ?string $method = null,
    ) {}
}
