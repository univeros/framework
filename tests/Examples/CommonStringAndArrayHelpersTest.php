<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Common\Support\Arr;
use Altair\Common\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/common/string-and-array-helpers.md
 * end-to-end. Asserts the Str truncate and Arr column extraction shapes.
 */
final class CommonStringAndArrayHelpersTest extends TestCase
{
    public function testStrTruncateAddsEllipsisSuffixForLongValues(): void
    {
        $str = new Str();

        self::assertSame(
            'The quick brown...',
            $str->truncate('The quick brown fox jumps over the lazy dog', 16),
        );
    }

    public function testStrTruncateLeavesShortValuesUntouched(): void
    {
        $str = new Str();

        self::assertSame('short', $str->truncate('short', 32));
    }

    public function testArrGetColumnExtractsValues(): void
    {
        $users = [
            ['id' => 1, 'email' => 'alice@example.com'],
            ['id' => 2, 'email' => 'bob@example.com'],
            ['id' => 3, 'email' => 'eve@example.com'],
        ];

        self::assertSame(
            ['alice@example.com', 'bob@example.com', 'eve@example.com'],
            array_values(Arr::getColumn($users, 'email')),
        );
    }
}
