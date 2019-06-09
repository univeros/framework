<?php

namespace Altair\Tests\Session;

use Altair\Filesystem\Filesystem;
use Altair\Session\CsrfToken;
use Altair\Session\Handler\FileSessionHandler;
use Altair\Session\SessionManager;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class CsrfTokenTest extends TestCase
{
    private $tmpDir;

    protected function setUp()
    {
        $this->tmpDir = __DIR__ . '/tmp';
        @mkdir($this->tmpDir);
    }

    protected function tearDown()
    {
        rmdir($this->tmpDir);
    }

    public function testCsrfToken()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getCookieParams()->willReturn([]);
        $manager = new SessionManager($request->reveal(), new FileSessionHandler(new Filesystem(), $this->tmpDir, 1));

        $prev = error_reporting(0);
        $token = $manager->getCsrfToken();
        error_reporting($prev);
        $this->assertInstanceOf(CsrfToken::class, $token);

        $csrf = $token->getValue();
        $this->assertNotEmpty($csrf);

        $newCsrf = $token->generateValue();
        $this->assertNotEquals($csrf, $newCsrf);
    }
}
