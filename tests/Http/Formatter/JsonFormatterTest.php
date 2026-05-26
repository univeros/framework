<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Formatter;

use Altair\Http\Base\Payload;
use Altair\Http\Formatter\JsonFormatter;
use PHPUnit\Framework\TestCase;

class JsonFormatterTest extends TestCase
{
    public function testAcceptsApplicationJson(): void
    {
        $this->assertSame(['application/json'], JsonFormatter::accepts());
    }

    public function testTypeReturnsApplicationJson(): void
    {
        $this->assertSame('application/json', (new JsonFormatter())->type());
    }

    public function testBodyEncodesPayloadOutputAsJson(): void
    {
        $payload = (new Payload())->withOutput(['name' => 'alice', 'age' => 30]);

        $body = (new JsonFormatter())->body($payload);

        $this->assertJson($body);
        $this->assertSame(['name' => 'alice', 'age' => 30], json_decode($body, true));
    }

    public function testBodyOfEmptyPayloadIsEmptyJsonObject(): void
    {
        $body = (new JsonFormatter())->body(new Payload());

        $this->assertSame('[]', $body);
    }
}
