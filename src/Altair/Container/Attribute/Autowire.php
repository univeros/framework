<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Attribute;

use Attribute;

/**
 * Wire the decorated parameter from an explicit source: another service id
 * (`#[Autowire(service: Clock::class)]`) or a registered container value /
 * parameter (`#[Autowire(param: 'app.locale')]`). Exactly one must be set.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Autowire
{
    public function __construct(
        public ?string $service = null,
        public ?string $param = null,
    ) {}
}
