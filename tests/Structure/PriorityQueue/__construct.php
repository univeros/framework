<?php
namespace Altair\Tests\Structure\PriorityQueue;

use Altair\Structure\PriorityQueue;

trait __construct
{
    public function testConstruct(): void
    {
        $this->assertToArray([], new PriorityQueue());
    }
}
