<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\AlphaNumFilter;

class AlphaNumFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            [0, '0'],
            [1, '1'],
            [2, '2'],
            [5, '5'],
            ['0', '0'],
            ['1', '1'],
            ['2', '2'],
            ['5', '5'],
            ['alphaonly', 'alphaonly'],
            ['AlphaOnLy', 'AlphaOnLy'],
            ['someThing8else', 'someThing8else'],
            ['soЗѝЦЯng8else', 'soЗѝЦЯng8else'],
            ['====$$%a', 'a'],
            ['9239939....', '9239939']
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new AlphaNumFilter();
    }
}
