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
    private string $tmpDir;

    #[\Override]
    protected function setUp(): void    {
        $this->tmpDir = __DIR__ . '/tmp';
        @mkdir($this->tmpDir);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tmpDir);
    }

    public function testCsrfToken(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn([]);
        $manager = new SessionManager($request, new FileSessionHandler(new Filesystem(), $this->tmpDir, 1));

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
