<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Exception;

use Altair\Http\Contracts\HttpExceptionInterface;
use Altair\Http\Contracts\ProblemExtensionInterface;
use Altair\Http\Exception\AuthorizationException;
use Altair\Http\Exception\AuthorizationTokenException;
use Altair\Http\Exception\HttpBadRequestException;
use Altair\Http\Exception\HttpException;
use Altair\Http\Exception\HttpMethodNotAllowedException;
use Altair\Http\Exception\HttpNotFoundException;
use Altair\Http\Exception\InputValidationException;
use PHPUnit\Framework\TestCase;

final class HttpExceptionStatusTest extends TestCase
{
    public function testHttpExceptionsAdvertiseTheirStatus(): void
    {
        $this->assertSame(404, (new HttpNotFoundException('/'))->getStatusCode());
        $this->assertSame(405, (new HttpMethodNotAllowedException(['GET'], 'nope', 405))->getStatusCode());
        $this->assertSame(401, (new AuthorizationException())->getStatusCode());
        $this->assertSame(403, (new AuthorizationTokenException())->getStatusCode());
        $this->assertSame(422, (new InputValidationException(['name' => 'required']))->getStatusCode());
    }

    public function testBareBadRequestFallsBackTo400NotServerError(): void
    {
        $this->assertSame(400, (new HttpBadRequestException('bad'))->getStatusCode());
    }

    public function testBareHttpExceptionFallsBackTo500(): void
    {
        $this->assertSame(500, (new HttpException('boom'))->getStatusCode());
    }

    public function testNonsenseCodeFallsBackToDefault(): void
    {
        // A code outside the HTTP error range must not leak through as a status.
        $this->assertSame(500, (new HttpException('boom', 7))->getStatusCode());
        $this->assertSame(400, (new HttpBadRequestException('boom', 7))->getStatusCode());
    }

    public function testMethodNotAllowedExposesAllowHeader(): void
    {
        $exception = new HttpMethodNotAllowedException(['GET', 'POST'], 'nope', 405);

        $this->assertSame(['Allow' => 'GET,POST'], $exception->getHeaders());
    }

    public function testMethodNotAllowedWithoutAllowListHasNoHeaders(): void
    {
        $this->assertSame([], (new HttpMethodNotAllowedException([], 'nope', 405))->getHeaders());
    }

    public function testBaseExceptionHasNoHeaders(): void
    {
        $this->assertSame([], (new HttpNotFoundException('/'))->getHeaders());
    }

    public function testValidationExceptionContributesProblemExtensions(): void
    {
        $exception = new InputValidationException(['email' => 'invalid', 'age' => 'required']);

        $this->assertInstanceOf(ProblemExtensionInterface::class, $exception);
        $this->assertSame(
            ['errors' => ['email' => 'invalid', 'age' => 'required']],
            $exception->getProblemExtensions(),
        );
    }

    public function testHttpExceptionsImplementTheContract(): void
    {
        $this->assertInstanceOf(HttpExceptionInterface::class, new HttpNotFoundException('/'));
        $this->assertInstanceOf(HttpExceptionInterface::class, new HttpException('x'));
    }
}
