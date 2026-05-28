<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Jwt;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A PSR-20 clock pinned to a fixed instant so token expiry can be asserted deterministically.
 */
final readonly class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public static function at(int $timestamp): self
    {
        return new self((new DateTimeImmutable())->setTimestamp($timestamp));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
