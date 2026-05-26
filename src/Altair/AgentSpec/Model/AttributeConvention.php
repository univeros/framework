<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Model;

/**
 * A discovered ATTRIBUTE_* class constant. Most often used in HTTP middleware
 * to advertise PSR-7 request attribute keys (e.g. `altair:http:ip-address`).
 */
final readonly class AttributeConvention
{
    public function __construct(
        public string $constantName,
        public string $value,
        public string $declaringClassShortName,
        public string $declaringClassFqcn,
    ) {}
}
