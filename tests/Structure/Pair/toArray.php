<?php
namespace Altair\Tests\Structure\Pair;

trait toArray
{
    public function testToArray(): void
    {
        $instance = $this->getPair('a', 1);
        $expected = [
            'key' => 'a',
            'value' => 1,
        ];

        $this->assertEquals($expected, $instance->toArray());
    }
}
