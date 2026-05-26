<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Support\MimeType;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;

class MimeTypeTest extends TestCase
{
    public function testGetFromResponseHeaderLineReturnsMimeFromContentType(): void
    {
        $response = (new Response('php://temp'))->withHeader('Content-Type', 'application/json; charset=utf-8');

        $this->assertSame('application/json', (new MimeType())->getFromResponseHeaderLine($response));
    }

    public function testGetFromResponseHeaderLineReturnsEmptyStringWhenHeaderAbsent(): void
    {
        // The implementation returns the trimmed first token of the Content-Type header
        // — when no header is set, that's the empty string. Callers should treat empty
        // as "unknown" and use MimeType::DEFAULT_MIME_TYPE.
        $this->assertSame('', (new MimeType())->getFromResponseHeaderLine(new Response()));
    }

    public function testGetFromFileExtensionResolvesKnownExtensions(): void
    {
        $this->assertSame('application/json', (new MimeType())->getFromFileExtension('data.json'));
        $this->assertSame('text/html', (new MimeType())->getFromFileExtension('index.html'));
        $this->assertSame('image/png', (new MimeType())->getFromFileExtension('logo.png'));
    }

    public function testGetFromFileExtensionFallsBackToDefaultForUnknownExtension(): void
    {
        $this->assertSame(MimeType::DEFAULT_MIME_TYPE, (new MimeType())->getFromFileExtension('mystery.xyz123'));
    }
}
