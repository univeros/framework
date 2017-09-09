<?php

namespace Altair\Tests\Session;

use Altair\Filesystem\Filesystem;
use Altair\Session\Handler\FileSessionHandler;
use PHPUnit\Framework\TestCase;

class FileSessionHandlerTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $fs;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->fs->makeDirectory(__DIR__ . '/tmp');
    }

    protected function tearDown()
    {
        $this->fs->deleteDirectory(__DIR__ . '/tmp');
    }

    public function testHandlerWithValidSessionTime()
    {
        $tmpDir = __DIR__ . '/tmp';

        $handler = new FileSessionHandler($this->fs, $tmpDir, 1);

        $this->assertTrue($handler->open('', '')); /* always returns true */

        $handler->write($this->getSessionId(), 'test-data');

        $content = $handler->read($this->getSessionId());

        $this->assertEquals('test-data', $content);

        $handler->destroy($this->getSessionId());

        $content = $handler->read($this->getSessionId());

        $this->assertEmpty($content);
    }

    public function testHandlerWithExpiredSessionTime()
    {
        $tmpDir = __DIR__ . '/tmp';

        $handler = new FileSessionHandler($this->fs, $tmpDir, 0); /*  no time */

        $handler->write($this->getSessionId(), 'test-data');

        sleep(1); /* delay 0.5 seconds - session should be finished */

        $content = $handler->read($this->getSessionId());
        $this->assertEmpty($content);

        $handler->gc(0); /* emulate gc */

        $this->assertFileNotExists($tmpDir . '/' . $this->getSessionId());
    }

    protected function getSessionId()
    {
        return 'aaaaaaaaaaaaaaaaaaaaaa';
    }
}
