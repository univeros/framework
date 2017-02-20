<?php
namespace Altair\Tests\Sanitation\Filter;


use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\AlphaFilter;

class AlphaFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['alphaOnly', 'alphaOnly'],
            ['AlphaOnly', 'AlphaOnly'],
            ['AlphaOnlyБуква', 'AlphaOnlyБуква'],
            ['самоБуква', 'самоБуква'],
            ['  ', ''],
            ['', ''],
            [0, ''],
            [1, ''],
            ['1', ''],
            ['5', ''],
            ['Nine 10 eleven', 'Nineeleven'],
            ['none: alpha-signs', 'nonealphasigns'],
            ['4alph4', 'alph'],
            ['Буква88', 'Буква'],
            [null, '']
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new AlphaFilter();
    }
}
