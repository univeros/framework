<?php

namespace Altair\Tests\Data;

use Altair\Data\Contracts\ArrayableInterface;
use Altair\Data\Contracts\DataObjectInterface;
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
     * @var DataObjectInterface
     */
    protected $entity;

    #[\Override]
    protected function setUp(): void    {
        $this->data = [
            'id' => 42,
            'name' => 'Vega',
            'created_at' => '2017-12-12 00:00:00',
        ];

        $this->entity = new Entity($this->data);
    }

    public function testInterfaces(): void
    {
        $this->assertInstanceOf(DataObjectInterface::class, $this->entity);
        $this->assertInstanceOf(JsonSerializable::class, $this->entity);
        $this->assertInstanceOf(ArrayableInterface::class, $this->entity);
        $this->assertInstanceOf(Serializable::class, $this->entity);
    }

    public function testDateAttribute(): void
    {
        $this->assertInstanceOf(Carbon::class, $this->entity->asCarbonDate('created_at'));
    }

    public function testJsonEncode(): void
    {
        $json = json_encode($this->entity);
        $this->assertJson($json);
        $this->assertSame($this->data, json_decode($json, true));
    }

    public function testSerialize(): void
    {
        $frozen = serialize($this->entity);

        $thawed = unserialize($frozen);
        $this->assertInstanceOf($this->entity::class, $thawed);
        $this->assertNotSame($this->entity, $thawed);
        $this->assertSame($this->data, $thawed->toArray());
    }

    public function testWithData(): void
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
