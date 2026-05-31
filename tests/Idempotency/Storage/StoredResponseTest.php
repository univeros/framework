<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Storage;

use Altair\Idempotency\Exception\IdempotencyException;
use Altair\Idempotency\Storage\StoredResponse;
use PHPUnit\Framework\TestCase;

final class StoredResponseTest extends TestCase
{
    public function testInProgressFactoryProducesPlaceholder(): void
    {
        $entry = StoredResponse::inProgress('hash123', 1_700_000_000);

        self::assertSame('hash123', $entry->requestHash);
        self::assertSame(0, $entry->status);
        self::assertSame([], $entry->headers);
        self::assertSame('', $entry->body);
        self::assertTrue($entry->inProgress);
        self::assertSame(1_700_000_000, $entry->createdAt);
    }

    public function testCompletedFactoryCarriesResponse(): void
    {
        $entry = StoredResponse::completed(
            requestHash: 'hash',
            status: 201,
            headers: ['Content-Type' => ['application/json']],
            body: '{"id":"u_1"}',
            createdAt: 1_700_000_100,
        );

        self::assertSame(201, $entry->status);
        self::assertSame('{"id":"u_1"}', $entry->body);
        self::assertFalse($entry->inProgress);
        self::assertSame(['Content-Type' => ['application/json']], $entry->headers);
    }

    public function testRoundTripsThroughArray(): void
    {
        $entry = StoredResponse::completed(
            requestHash: 'hash',
            status: 200,
            headers: ['X-Trace' => ['abc-123']],
            body: 'ok',
            createdAt: 1_700_000_000,
        );

        $rebuilt = StoredResponse::fromArray($entry->toArray());

        self::assertSame($entry->requestHash, $rebuilt->requestHash);
        self::assertSame($entry->status, $rebuilt->status);
        self::assertSame($entry->headers, $rebuilt->headers);
        self::assertSame($entry->body, $rebuilt->body);
        self::assertSame($entry->inProgress, $rebuilt->inProgress);
        self::assertSame($entry->createdAt, $rebuilt->createdAt);
    }

    public function testRoundTripsThroughJson(): void
    {
        $entry = StoredResponse::completed('h', 200, ['Content-Type' => ['text/plain']], 'hi', 42);

        $rebuilt = StoredResponse::fromJson($entry->toJson());

        self::assertSame($entry->body, $rebuilt->body);
        self::assertSame($entry->headers, $rebuilt->headers);
    }

    public function testFromArrayRejectsMissingFields(): void
    {
        $this->expectException(IdempotencyException::class);
        $this->expectExceptionMessage('missing required field "body"');

        StoredResponse::fromArray([
            'request_hash' => 'h',
            'status' => 200,
            'headers' => [],
            // body missing
            'in_progress' => false,
            'created_at' => 0,
        ]);
    }

    public function testFromArrayRejectsNonArrayHeaders(): void
    {
        $this->expectException(IdempotencyException::class);
        $this->expectExceptionMessage('"headers" must be an array');

        StoredResponse::fromArray([
            'request_hash' => 'h',
            'status' => 200,
            'headers' => 'oops',
            'body' => '',
            'in_progress' => false,
            'created_at' => 0,
        ]);
    }

    public function testFromArraySkipsMalformedHeaderRows(): void
    {
        $entry = StoredResponse::fromArray([
            'request_hash' => 'h',
            'status' => 200,
            'headers' => [
                'Content-Type' => ['application/json'],
                42 => ['skipped because numeric key'],
                'X-Bad' => 'not a list, skipped',
            ],
            'body' => '',
            'in_progress' => false,
            'created_at' => 0,
        ]);

        self::assertSame(['Content-Type' => ['application/json']], $entry->headers);
    }

    public function testFromJsonRejectsMalformedJson(): void
    {
        $this->expectException(IdempotencyException::class);
        $this->expectExceptionMessage('JSON is malformed');

        StoredResponse::fromJson('{not json');
    }

    public function testFromJsonRejectsNonMap(): void
    {
        $this->expectException(IdempotencyException::class);
        $this->expectExceptionMessage('must decode to a map');

        StoredResponse::fromJson('"a string"');
    }
}
