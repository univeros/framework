<?php
namespace Altair\tests\Structure\Pair;

use Altair\Structure\Pair;

trait __construct
{
    public function testConstruct()
    {
        $pair = new Pair('a', 1);
        $this->assertEquals('a', $pair->key);
        $this->assertEquals(1,  $pair->value);
    }
}
