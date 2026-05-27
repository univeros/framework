<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Attribute;

use Altair\Tests\Messaging\Fixtures\SendWelcomeEmailHandler;
use Altair\Messaging\Attribute\AsHandler;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmail;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AsHandlerTest extends TestCase
{
    public function testAttributeIsReadableFromHandlerClass(): void
    {
        $reflection = new ReflectionClass(SendWelcomeEmailHandler::class);
        $attributes = $reflection->getAttributes(AsHandler::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertSame(SendWelcomeEmail::class, $attribute->messageClass);
        $this->assertSame('__invoke', $attribute->method ?? '__invoke');
        $this->assertNull($attribute->fromTransport);
        $this->assertSame(0, $attribute->priority);
    }

    public function testDefaultsAreSensible(): void
    {
        $attribute = new AsHandler(SendWelcomeEmail::class);

        $this->assertSame(SendWelcomeEmail::class, $attribute->messageClass);
        $this->assertNull($attribute->fromTransport);
        $this->assertSame(0, $attribute->priority);
        $this->assertNull($attribute->method);
    }
}
