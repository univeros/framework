<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Discovery;

use Altair\Messaging\Discovery\AttributeHandlerDiscoverer;
use Altair\Messaging\Exception\InvalidArgumentException;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmail;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmailHandler;
use PHPUnit\Framework\TestCase;

class AttributeHandlerDiscovererTest extends TestCase
{
    public function testDiscoversAttributedHandler(): void
    {
        $discoverer = new AttributeHandlerDiscoverer();
        $found = iterator_to_array($discoverer->scan([__DIR__ . '/../Fixtures']), false);

        $classes = array_map(static fn(array $e): string => $e['class'], $found);
        $this->assertContains(SendWelcomeEmailHandler::class, $classes);
    }

    public function testIgnoresClassesWithoutAttribute(): void
    {
        $discoverer = new AttributeHandlerDiscoverer();
        $found = iterator_to_array($discoverer->scan([__DIR__ . '/../Fixtures']), false);

        $classes = array_map(static fn(array $e): string => $e['class'], $found);
        $this->assertNotContains(\Altair\Tests\Messaging\Fixtures\NotAHandler::class, $classes);
        $this->assertNotContains(SendWelcomeEmail::class, $classes);
    }

    public function testBuildRegistryProducesEntries(): void
    {
        $registry = (new AttributeHandlerDiscoverer())
            ->buildRegistry([__DIR__ . '/../Fixtures']);

        $entries = $registry->handlersFor(SendWelcomeEmail::class);
        $this->assertCount(1, $entries);
        $this->assertSame(SendWelcomeEmailHandler::class, $entries[0]->handlerClass);
    }

    public function testRejectsUnreadableDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(
            (new AttributeHandlerDiscoverer())->scan(['/no/such/path']),
            false,
        );
    }
}
