<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Support\HeaderTokenExtractor;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class HeaderTokenExtractorTest extends TestCase
{
    public function testExtractsTokenFromConfiguredHeader(): void
    {
        $extractor = new HeaderTokenExtractor('X-Auth-Token');
        $request = (new ServerRequest())->withHeader('X-Auth-Token', 'tok-123');

        $this->assertSame('tok-123', $extractor->extract($request));
    }

    public function testReturnsNullWhenHeaderAbsent(): void
    {
        $extractor = new HeaderTokenExtractor('X-Auth-Token');

        $this->assertNull($extractor->extract(new ServerRequest()));
    }

    public function testReturnsNullWhenHeaderEmpty(): void
    {
        $extractor = new HeaderTokenExtractor('X-Auth-Token');
        $request = (new ServerRequest())->withHeader('X-Auth-Token', '');

        $this->assertNull($extractor->extract($request));
    }
}
