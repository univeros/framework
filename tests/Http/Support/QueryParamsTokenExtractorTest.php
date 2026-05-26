<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Support\QueryParamsTokenExtractor;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class QueryParamsTokenExtractorTest extends TestCase
{
    public function testExtractsTokenFromConfiguredQueryParam(): void
    {
        $extractor = new QueryParamsTokenExtractor('token');
        $request = (new ServerRequest())->withQueryParams(['token' => 'tok-abc']);

        $this->assertSame('tok-abc', $extractor->extract($request));
    }

    public function testReturnsNullWhenParamAbsent(): void
    {
        $extractor = new QueryParamsTokenExtractor('token');
        $request = (new ServerRequest())->withQueryParams(['other' => 'x']);

        $this->assertNull($extractor->extract($request));
    }
}
