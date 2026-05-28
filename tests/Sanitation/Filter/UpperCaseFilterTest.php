<?php

declare(strict_types=1);

namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\UpperCaseFilter;

class UpperCaseFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['AbcDe', 'ABCDE'],
            ['1234', '1234'],
            ['Antonio', 'ANTONIO'],
            [[], null],
            ['US', 'US'],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new UpperCaseFilter();
    }
}
