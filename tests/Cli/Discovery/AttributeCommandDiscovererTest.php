<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Discovery;

use Altair\Cli\Discovery\AttributeCommandDiscoverer;
use Altair\Cli\Exception\InvalidArgumentException;
use Altair\Tests\Cli\Discovery\fixtures\CreateUserCommand;
use Altair\Tests\Cli\Discovery\fixtures\Nested\NestedCommand;
use Altair\Tests\Cli\Discovery\fixtures\NotACommand;
use PHPUnit\Framework\TestCase;

class AttributeCommandDiscovererTest extends TestCase
{
    public function testFindsAttributedClassesInRoot(): void
    {
        $discoverer = new AttributeCommandDiscoverer();
        $found = iterator_to_array($discoverer->scan([__DIR__ . '/fixtures']));

        $this->assertContains(CreateUserCommand::class, $found);
    }

    public function testRecursesIntoSubdirectories(): void
    {
        $discoverer = new AttributeCommandDiscoverer();
        $found = iterator_to_array($discoverer->scan([__DIR__ . '/fixtures']));

        $this->assertContains(NestedCommand::class, $found);
    }

    public function testIgnoresClassesWithoutCommandAttribute(): void
    {
        $discoverer = new AttributeCommandDiscoverer();
        $found = iterator_to_array($discoverer->scan([__DIR__ . '/fixtures']));

        $this->assertNotContains(NotACommand::class, $found);
    }

    public function testDeduplicatesAcrossMultiplePaths(): void
    {
        $discoverer = new AttributeCommandDiscoverer();
        $found = iterator_to_array(
            $discoverer->scan([__DIR__ . '/fixtures', __DIR__ . '/fixtures']),
        );

        $this->assertCount(
            count(array_unique($found)),
            $found,
            'Discoverer must not yield duplicate classes across overlapping paths.',
        );
    }

    public function testThrowsOnUnreadableDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $discoverer = new AttributeCommandDiscoverer();
        iterator_to_array($discoverer->scan(['/non/existent/path']));
    }
}
