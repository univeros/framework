<?php
namespace Altair\Tests\Structure\Set;

trait count
{
    public function testCount()
    {
        list($unique, $duplicates) = static::getUniqueAndDuplicateData();

        $instance = static::getInstance($unique);
        $this->assertCount(count($unique), $instance);

        $instance = static::getInstance($duplicates);
        $this->assertCount(count($unique), $instance);
    }

    public function testCountEmpty()
    {
        $instance = static::getInstance();
        $this->assertCount(0, $instance);
    }
}
