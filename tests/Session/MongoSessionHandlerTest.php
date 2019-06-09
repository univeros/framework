<?php

namespace Altair\Tests\Session;

use Altair\Session\Handler\MongoSessionHandler;
use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;

class MongoSessionHandlerTest extends TestCase
{
    /**
     * @var Collection
     */
    private $collection;
    /**
     * @var MongoSessionHandler
     */
    private $handler;

    protected function setUp()
    {
        $client = new Client();
        $this->collection = $client->selectCollection('sessionhandlertest', 'sessions');
        $this->handler = new MongoSessionHandler($this->collection);
    }

    protected function tearDown()
    {
        $this->collection->drop();
    }

    public function testOpenReturnsTrue()
    {
        $this->assertTrue($this->handler->open('', 'sid'));
    }

    public function testCloseReturnsTrue()
    {
        $this->assertTrue($this->handler->close());
    }

    public function testReadWriteData()
    {
        $handler = $this->handler;
        $handler->open('', 'sid');
        $data = $handler->read('id');
        $this->assertEmpty($data);

        $handler->write('id', 'data');
        $handler->close();
        $this->assertEquals(1, $this->collection->count());

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $handler->close();
        $this->assertEquals('data', $data);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionGc()
    {
        $previousLifeTime = ini_set('session.gc_maxlifetime', 1000);

        $handler = $this->handler;
        $handler->open('', 'sid');
        $handler->read('id');
        $handler->write('id', 'data');
        $handler->close();

        $handler->open('', 'sid');
        $handler->read('gc_id');
        ini_set('session.gc_maxlifetime', -1); // test that you can set lifetime of a session after it has been read
        $handler->write('gc_id', 'data');
        $handler->close();
        $this->assertEquals(2, $this->collection->count());

        $handler->open('', 'sid');
        $data = $handler->read('gc_id');
        $handler->gc(-1);
        $handler->close();

        ini_set('session.gc_maxlifetime', $previousLifeTime);

        $this->assertSame('', $data, 'Sessions already considered garbage, so not returning data even if it is not pruned yet');
        $this->assertEquals(1, $this->collection->count(), 'Expired sessions are pruned');
    }

    public function testSessionDestroy()
    {
        $handler = $this->handler;

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $this->assertEmpty($data);

        $handler->write('id', 'data');
        $handler->close();
        $this->assertEquals(1, $this->collection->count());

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $this->assertEquals('data', $data);
        $handler->destroy('id');
        $handler->close();
        $this->assertEquals(0, $this->collection->count());

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $handler->close();

        $this->assertSame('', $data, 'Destroyed session returns empty string');
    }
}
