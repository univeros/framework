<?php

namespace Altair\Tests\Data;

use Altair\Data\Contracts\ArrayableInterface;
use Altair\Data\Contracts\EntityInterface;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use JsonSerializable;
use Serializable;

class EntityTest extends TestCase
{
    /**
     * @var array
     */
    protected $data;
    /**
     * @var EntityInterface
     */
    protected $entity;

    protected function setUp()
    {
        $this->data = [
            'id' => 42,
            'name' => 'Vega',
            'created_at' => '2017-12-12 00:00:00',
        ];

        $this->entity = new Entity($this->data);
    }

    public function testInterfaces()
    {
        $this->assertInstanceOf(EntityInterface::class, $this->entity);
        $this->assertInstanceOf(JsonSerializable::class, $this->entity);
        $this->assertInstanceOf(ArrayableInterface::class, $this->entity);
        $this->assertInstanceOf(Serializable::class, $this->entity);
    }

    public function testDateAttribute()
    {
        $this->assertInstanceOf(Carbon::class, $this->entity->asCarbonDate('created_at'));
    }

    public function testJsonEncode()
    {
        $json = json_encode($this->entity);
        $this->assertJson($json);
        $this->assertSame($this->data, json_decode($json, true));
    }

    public function testSerialize()
    {
        $frozen = serialize($this->entity);

        $thawed = unserialize($frozen);
        $this->assertInstanceOf(get_class($this->entity), $thawed);
        $this->assertNotSame($this->entity, $thawed);
        $this->assertSame($this->data, $thawed->toArray());
    }

    public function testWithData()
    {
        $data = [
            'id' => 43,
            'name' => 'Moon',
            'created_at' => null,
        ];

        $entity = $this->entity->withData($data);

        $this->assertNotSame($entity, $this->entity);
        $this->assertEquals(43, $entity->id);
        $this->assertEquals('Moon', $entity->name);
        $this->assertNull($entity->created_at);
    }
}
