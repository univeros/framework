<?php

namespace Altair\Tests\Middleware;

use Altair\Middleware\Payload;
use PHPUnit\Framework\TestCase;

class PayloadTest extends TestCase
{
    public function testAttribute(): void
    {
        $payload = new Payload(['attr1'=> 1]);

        $this->assertSame(1, $payload->getAttribute('attr1'));
        $this->assertNull($payload->getAttribute('unknown'));

        $new = $payload->withAttribute('attr2', 2);

        $this->assertNotSame($new, $payload);
        $this->assertNull($payload->getAttribute('attr2'));
        $this->assertSame(2, $new->getAttribute('attr2'));
    }

    public function testAttributes(): void
    {
        $attrs = ['a' => 1, 'b' => 2, 'c' => 3];
        $payload = new Payload($attrs);

        $this->assertSame($attrs, $payload->getAttributes());

        $newAttrs = ['d' => 4, 'e' => 5];
        $new = $payload->withAttributes($newAttrs);

        $this->assertNotSame($new, $payload);
        $this->assertNull($payload->getAttribute('d'));
        $this->assertNull($new->getAttribute('b'));
        $this->assertSame(5, $new->getAttribute('e'));
        $this->assertSame($newAttrs, $new->getAttributes());
    }

    public function testWithoutAttributeReturnsCloneWithKeyRemoved(): void
    {
        $payload = new Payload(['a' => 1, 'b' => 2]);

        $new = $payload->withoutAttribute('a');

        $this->assertNotSame($new, $payload);
        $this->assertSame(['a' => 1, 'b' => 2], $payload->getAttributes());
        $this->assertSame(['b' => 2], $new->getAttributes());
    }

    public function testWithoutAttributeIsNoOpForMissingKey(): void
    {
        $payload = new Payload(['a' => 1]);

        $new = $payload->withoutAttribute('does-not-exist');

        $this->assertNotSame($new, $payload);
        $this->assertSame(['a' => 1], $new->getAttributes());
    }
}
