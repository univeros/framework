<?php
namespace Altair\Tests\Structure\Map;

trait toArray
{
    public function testToArrayWithBadKey()
    {
        $instance = static::getInstance();
        $instance->put(new \stdClass(), 1);

        $this->expectInternalIllegalOffset();
        $instance->toArray();
    }
}
