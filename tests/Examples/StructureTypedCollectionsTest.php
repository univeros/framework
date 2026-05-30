<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Structure\Map;
use Altair\Structure\Vector;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/structure/typed-collections.md
 * end-to-end. Asserts Map keyed access and Vector sequence operations.
 */
final class StructureTypedCollectionsTest extends TestCase
{
    public function testMapKeyedAccess(): void
    {
        $active = new Map();
        $active->put('alice', ['status' => 'online']);
        $active->put('bob', ['status' => 'away']);

        self::assertSame(['status' => 'online'], $active->get('alice'));
        self::assertFalse($active->hasKey('charlie'));
        self::assertSame(2, $active->count());
    }

    public function testVectorSequenceOperations(): void
    {
        $totals = new Vector([10, 20, 30, 40]);

        $totals->push(50);

        self::assertSame(5, $totals->count());
        self::assertSame([10, 20, 30, 40, 50], $totals->toArray());
    }
}
