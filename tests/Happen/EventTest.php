<?php

declare(strict_types=1);

namespace Altair\Tests\Happen;

use Altair\Happen\Event;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;

class EventTest extends TestCase
{
    public function testConstructorSetsNameAndArguments(): void
    {
        $event = new Event('user.created', ['id' => 42]);

        $this->assertSame('user.created', $event->getName());
        $this->assertSame(['id' => 42], $event->getArguments());
    }

    public function testConstructorAllowsNullArguments(): void
    {
        $event = new Event('user.created');

        $this->assertSame([], $event->getArguments());
    }

    public function testOccurredOnIsSetOnConstruction(): void
    {
        $event = new Event('user.created');

        $this->assertGreaterThan(0, $event->getOccurredOn());
    }

    public function testPropagationIsNotStoppedByDefault(): void
    {
        $event = new Event('user.created');

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationMarksEventAsStoppedAndReturnsEvent(): void
    {
        $event = new Event('user.created');

        $result = $event->stopPropagation();

        $this->assertSame($event, $result);
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testImplementsPsr14StoppableEventInterface(): void
    {
        $event = new Event('user.created');

        $this->assertInstanceOf(StoppableEventInterface::class, $event);
    }

    public function testHasArgumentReturnsTrueForKnownKey(): void
    {
        $event = new Event('user.created', ['id' => 42]);

        $this->assertTrue($event->hasArgument('id'));
    }

    public function testHasArgumentIsCaseInsensitive(): void
    {
        $event = new Event('user.created', ['id' => 42]);

        $this->assertTrue($event->hasArgument('ID'));
        $this->assertTrue($event->hasArgument('Id'));
    }

    public function testHasArgumentReturnsFalseForUnknownKey(): void
    {
        $event = new Event('user.created');

        $this->assertFalse($event->hasArgument('missing'));
    }

    public function testGetArgumentReturnsValue(): void
    {
        $event = new Event('user.created', ['id' => 42]);

        $this->assertSame(42, $event->getArgument('id'));
    }

    public function testGetArgumentIsCaseInsensitive(): void
    {
        $event = new Event('user.created', ['id' => 42]);

        $this->assertSame(42, $event->getArgument('ID'));
    }

    public function testGetArgumentThrowsForUnknownKey(): void
    {
        $event = new Event('user.created');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing/');

        $event->getArgument('missing');
    }

    public function testWithNameReturnsNewInstanceWithName(): void
    {
        $original = new Event('user.created', ['id' => 42]);

        $renamed = $original->withName('user.updated');

        $this->assertNotSame($original, $renamed);
        $this->assertSame('user.created', $original->getName());
        $this->assertSame('user.updated', $renamed->getName());
        $this->assertSame(['id' => 42], $renamed->getArguments());
    }

    public function testWithArgumentReturnsNewInstance(): void
    {
        $original = new Event('user.created', ['id' => 42]);

        $extended = $original->withArgument('name', 'alice');

        $this->assertNotSame($original, $extended);
        $this->assertFalse($original->hasArgument('name'));
        $this->assertTrue($extended->hasArgument('name'));
        $this->assertSame('alice', $extended->getArgument('name'));
    }

    public function testWithArgumentsReplacesAllArguments(): void
    {
        $original = new Event('user.created', ['id' => 42]);

        $replaced = $original->withArguments(['name' => 'alice']);

        $this->assertSame(['name' => 'alice'], $replaced->getArguments());
        $this->assertSame(['id' => 42], $original->getArguments());
    }

    public function testWithNameReturnsAnEventInterface(): void
    {
        $event = (new Event('a'))->withName('b');

        $this->assertInstanceOf(EventInterface::class, $event);
    }
}
