<?php

namespace Altair\Tests\Session;

use Altair\Filesystem\Filesystem;
use Altair\Session\Handler\FileSessionHandler;
use PHPUnit\Framework\TestCase;

class FileSessionHandlerTest extends TestCase
{
    private Filesystem $fs;

    #[\Override]
    protected function setUp(): void    {
        $this->fs = new Filesystem();
        $this->fs->makeDirectory(__DIR__ . '/tmp');
    }

    #[\Override]
    protected function tearDown(): void    {
        $this->fs->deleteDirectory(__DIR__ . '/tmp');
    }

    public function testHandlerWithValidSessionTime(): void
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

    public function testHandlerWithExpiredSessionTime(): void
    {
        $tmpDir = __DIR__ . '/tmp';

        $handler = new FileSessionHandler($this->fs, $tmpDir, 0); /*  no time */

        $handler->write($this->getSessionId(), 'test-data');

        sleep(1); /* delay 0.5 seconds - session should be finished */

        $content = $handler->read($this->getSessionId());
        $this->assertEmpty($content);

        $handler->gc(0); /* emulate gc */

        $this->assertFileDoesNotExist($tmpDir . '/' . $this->getSessionId());
    }

    protected function getSessionId(): string
    {
        return 'aaaaaaaaaaaaaaaaaaaaaa';
    }
}
