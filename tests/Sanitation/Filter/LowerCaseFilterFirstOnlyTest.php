<?php

declare(strict_types=1);

namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\LowerCaseFilter;

class LowerCaseFilterFirstOnlyTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['AbcDe', 'abcDe'],
            ['1234', '1234'],
            ['AnToniO', 'anToniO'],
            [[], null],
            ['US', 'uS'],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new LowerCaseFilter(true);
    }
}
