<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Support;

use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Support\FormatNegotiator;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormatNegotiator::class)]
final class FormatNegotiatorTest extends TestCase
{
    public function testReturnsPrimaryContentTypeForRegisteredFormat(): void
    {
        $negotiator = new FormatNegotiator();

        self::assertSame('application/json', $negotiator->getContentTypeByFormat('json'));
        self::assertSame('text/html', $negotiator->getContentTypeByFormat('html'));
    }

    public function testThrowsWhenFormatIsNotRegistered(): void
    {
        $negotiator = new FormatNegotiator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown format "definitely-not-a-format"');

        $negotiator->getContentTypeByFormat('definitely-not-a-format');
    }

    public function testNegotiatesFormatFromAcceptHeader(): void
    {
        $negotiator = new FormatNegotiator();
        $request = (new ServerRequest())->withHeader('Accept', 'application/json, text/html;q=0.5');

        self::assertSame('json', $negotiator->getFromServerRequestHeaderLine($request));
    }

    public function testReturnsNullWhenAcceptHeaderIsAbsent(): void
    {
        $negotiator = new FormatNegotiator();

        self::assertNull($negotiator->getFromServerRequestHeaderLine(new ServerRequest()));
    }
}
