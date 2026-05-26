<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Exception\RuntimeException;
use Altair\Http\Middleware\SpamBlockerMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SpamBlockerMiddlewareTest extends AbstractMiddlewareTest
{
    private string $spammersFile;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->spammersFile = tempnam(sys_get_temp_dir(), 'spammers');
        file_put_contents($this->spammersFile, "spammer.example\nbad-actor.net\n");
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->spammersFile);
    }

    public function testConstructorThrowsWhenSpammersFileMissing(): void
    {
        $this->expectException(RuntimeException::class);

        new SpamBlockerMiddleware(new ResponseFactory(), '/does/not/exist');
    }

    public function testRequestFromBlockedRefererReturns403(): void
    {
        $middleware = new SpamBlockerMiddleware(new ResponseFactory(), $this->spammersFile);
        $request = (new ServerRequest())->withHeader('Referer', 'https://spammer.example/page');

        $response = $this->dispatch([$middleware, $this->okHandler()], $request);

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRequestFromCleanRefererPassesThrough(): void
    {
        $middleware = new SpamBlockerMiddleware(new ResponseFactory(), $this->spammersFile);
        $request = (new ServerRequest())->withHeader('Referer', 'https://example.org/page');

        $response = $this->dispatch([$middleware, $this->okHandler()], $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWwwPrefixIsNormalizedBeforeMatch(): void
    {
        $middleware = new SpamBlockerMiddleware(new ResponseFactory(), $this->spammersFile);
        $request = (new ServerRequest())->withHeader('Referer', 'https://www.spammer.example/page');

        $response = $this->dispatch([$middleware, $this->okHandler()], $request);

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function okHandler(): PsrMiddlewareInterface
    {
        return new class () implements PsrMiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response('php://temp', 200);
            }
        };
    }
}
