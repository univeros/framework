<?php

namespace Altair\Tests\Session;

use Altair\Session\Handler\MongoSessionHandler;
use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
class MongoSessionHandlerTest extends TestCase
{
    /**
     * @var Collection
     */
    private $collection;

    private MongoSessionHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        if (!extension_loaded('mongodb')) {
            $this->markTestSkipped('ext-mongodb is not loaded.');
        }

        $client = new Client();
        $this->collection = $client->selectCollection('sessionhandlertest', 'sessions');
        $this->handler = new MongoSessionHandler($this->collection);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->collection?->drop();
    }

    public function testOpenReturnsTrue(): void
    {
        $this->assertTrue($this->handler->open('', 'sid'));
    }

    public function testCloseReturnsTrue(): void
    {
        $this->assertTrue($this->handler->close());
    }

    public function testReadWriteData(): void
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

    #[RunInSeparateProcess]
    public function testSessionGc(): void
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

    public function testSessionDestroy(): void
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
