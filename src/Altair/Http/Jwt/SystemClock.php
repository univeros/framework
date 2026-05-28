<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Jwt;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A PSR-20 clock backed by the system wall clock.
 *
 * Used as the default time source for JWT expiry validation; inject a different
 * {@see ClockInterface} (for example a frozen clock in tests) to control time.
 */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
