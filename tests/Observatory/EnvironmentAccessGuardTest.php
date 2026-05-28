<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory;

use Altair\Observatory\Security\EnvironmentAccessGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvironmentAccessGuard::class)]
final class EnvironmentAccessGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string, bool}>
     */
    public static function cases(): iterable
    {
        yield 'enabled + local => allowed' => [true, 'local', true];
        yield 'enabled + testing => allowed' => [true, 'testing', true];
        yield 'enabled + production => denied' => [true, 'production', false];
        yield 'enabled + unknown env => denied' => [true, 'staging', false];
        yield 'disabled + local => denied' => [false, 'local', false];
        yield 'disabled + production => denied' => [false, 'production', false];
    }

    #[DataProvider('cases')]
    public function testAllows(bool $enabled, string $environment, bool $expected): void
    {
        self::assertSame($expected, (new EnvironmentAccessGuard($enabled, $environment))->allows());
    }

    public function testFailsClosedForProductionEvenWhenEnabled(): void
    {
        // The critical security property: a production deploy is never exposed
        // just because the flag was flipped on.
        self::assertFalse((new EnvironmentAccessGuard(true, 'production'))->allows());
    }
}
