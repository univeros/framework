<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Hash;

use Altair\Idempotency\Hash\RequestBodyHasher;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\TestCase;

final class RequestBodyHasherTest extends TestCase
{
    public function testHashesBodyBytes(): void
    {
        $request = $this->requestWithBody('{"a":1}');

        $hash = (new RequestBodyHasher())->hash($request);

        self::assertSame(hash('sha256', '{"a":1}'), $hash);
    }

    public function testDifferentBodiesProduceDifferentHashes(): void
    {
        $a = (new RequestBodyHasher())->hash($this->requestWithBody('{"a":1}'));
        $b = (new RequestBodyHasher())->hash($this->requestWithBody('{"a":2}'));

        self::assertNotSame($a, $b);
    }

    public function testWhitespaceCountsTowardsHash(): void
    {
        // Semantically equivalent JSON bodies produce different hashes.
        // Applications that want canonical hashing add a canonicalising
        // middleware upstream of this one.
        $a = (new RequestBodyHasher())->hash($this->requestWithBody('{"a":1}'));
        $b = (new RequestBodyHasher())->hash($this->requestWithBody('{"a": 1}'));

        self::assertNotSame($a, $b);
    }

    public function testRewindsBodyForDownstreamConsumers(): void
    {
        $request = $this->requestWithBody('{"a":1}');
        $hasher = new RequestBodyHasher();

        $hasher->hash($request);

        $body = $request->getBody();
        $body->rewind();
        self::assertSame('{"a":1}', (string) $body);
    }

    public function testEmptyBodyHashesDeterministically(): void
    {
        $hash = (new RequestBodyHasher())->hash($this->requestWithBody(''));

        self::assertSame(hash('sha256', ''), $hash);
    }

    private function requestWithBody(string $contents): ServerRequest
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write($contents);
        $stream->rewind();

        return (new ServerRequest())->withBody($stream);
    }
}
