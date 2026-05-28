<?php

declare(strict_types=1);

namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\UpperCaseFilter;

class UpperCaseFilterFirstOnlyTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['AbcDe', 'AbcDe'],
            ['1234', '1234'],
            ['anToniO', 'AnToniO'],
            [[], null],
            ['US', 'US'],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new UpperCaseFilter(true);
    }
}
