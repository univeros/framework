<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\TitleCaseFilter;

class TitleCaseFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['antonio', 'Antonio'],
            ['123', '123'],
            [[], null],
            ['antonio ramirez', 'Antonio Ramirez'],
            ['US', 'US'],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new TitleCaseFilter();
    }
}
