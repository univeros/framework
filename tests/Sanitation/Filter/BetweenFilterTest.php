<?php

declare(strict_types=1);

namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\BetweenFilter;

class BetweenFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            [2, 2],
            [3, 3],
            [4, 4],
            [5, 5],
            [6, 6],
            [-1, 2],
            [1, 2],
            [7, 6]
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new BetweenFilter(2, 6);
    }
}
