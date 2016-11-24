<?php
namespace Altair\tests\Structure\PriorityQueue;

use Altair\Structure\PriorityQueue;

trait __construct
{
    public function testConstruct()
    {
        $this->assertToArray([], new PriorityQueue());
    }
}
