<?php

namespace Altair\Tests\Session;

use Altair\Session\SessionBlock;
use Altair\Session\SessionManager;
use PHPUnit\Framework\TestCase;

class SessionBlockTest extends TestCase
{
    public function testGetSetHasAndRemoveMethods()
    {
        $sessionBlock = $this->getSessionBlock();

        $data = $sessionBlock->get('test');
        $this->assertNull($data);
        $this->assertFalse($sessionBlock->has('test'));

        $sessionBlock->set('test', 'value');
        $this->assertTrue($sessionBlock->has('test'));
        $data = $sessionBlock->get('test');
        $this->assertEquals('value', $data);

        $sessionBlock->remove('test');
        $data = $sessionBlock->get('test');
        $this->assertNull($data);
        $this->assertFalse($sessionBlock->has('test'));
    }

    public function testAssertThatClearAndSetReturnSessionBlockInstances()
    {
        $sessionBlock = $this->getSessionBlock();

        $instance = $sessionBlock->set('test', 'value');
        $this->assertEquals($sessionBlock, $instance);

        $instance = $sessionBlock->clear();
        $this->assertEquals($sessionBlock, $instance);
    }

    public function testCanSetAndGetFlash()
    {
        $sessionBlock = $this->getSessionBlock();

        $sessionBlock->setFlash('name', 'value');
        $this->assertTrue($sessionBlock->hasFlash('name'));
        $message = $sessionBlock->getFlash('name');
        $this->assertEquals('value', $message);

        $default = $sessionBlock->getFlash('unknown', false);
        $this->assertFalse($default);

        $sessionBlock->getFlash('name', null, true);
        $this->assertFalse($sessionBlock->hasFlash('name'));
    }

    public function testFlashesGetRemovedAfterNewRequest()
    {
        $sessionBlock = $this->getSessionBlock();
        $sessionBlock->setFlash('name', 'value');
        $this->assertTrue($sessionBlock->hasFlash('name'));
        $message = $sessionBlock->getFlash('name');
        $this->assertEquals('value', $message);

        // mimic new request (on create it calls UpdateFlashCounters that removes already used flash messages)
        $sessionBlock = $this->getSessionBlock();
        $this->assertFalse($sessionBlock->has('name'));
    }

    public function testAppendFlashMessagesCreatesArray()
    {
        $sessionBlock = $this->getSessionBlock();
        $sessionBlock->appendFlash('name', 'value');
        $sessionBlock->appendFlash('name', 'another value');
        $this->assertCount(2, $sessionBlock->getFlash('name'));
        $this->assertCount(1, $sessionBlock->getAllFlashes());

        $data = $sessionBlock->getFlash('name');
        $this->assertContains('value', $data);
        $this->assertContains('another value', $data);
    }

    public function testFlashRemoval()
    {
        $sessionBlock = $this->getSessionBlock();
        $sessionBlock->appendFlash('name', 'value');
        $sessionBlock->appendFlash('name', 'another value');
        $sessionBlock->setFlash('foo', 'bar');

        $this->assertCount(2, $sessionBlock->getAllFlashes());

        $sessionBlock->removeAllFlashes();
        $this->assertCount(0, $sessionBlock->getAllFlashes());

        $sessionBlock->setFlash('foo', 'bar');
        $this->assertTrue($sessionBlock->has('foo'));
        $sessionBlock->remove('foo');
        $this->assertFalse($sessionBlock->has('foo'));
        $this->assertCount(0, $sessionBlock->getAllFlashes());
    }

    /**
     * @return SessionBlock
     */
    protected function getSessionBlock()
    {
        $manager = $this->mockSessionManager();
        $manager->method('start')->willReturn(true);

        return new SessionBlock('name', $manager);
    }

    protected function mockSessionManager()
    {
        return $this->getMockBuilder(SessionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
