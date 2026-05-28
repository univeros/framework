<?php

declare(strict_types=1);

namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\LowerCaseFilter;

class LowerCaseFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['AbcDe', 'abcde'],
            ['1234', '1234'],
            ['Antonio', 'antonio'],
            [[], null],
            ['US', 'us'],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new LowerCaseFilter();
    }
}
