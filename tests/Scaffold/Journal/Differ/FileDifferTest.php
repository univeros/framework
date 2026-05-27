<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Journal\Differ;

use Altair\Scaffold\Journal\Differ\FileDiffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileDiffer::class)]
class FileDifferTest extends TestCase
{
    public function testEqualInputsProduceEmptyDiff(): void
    {
        $this->assertSame('', (new FileDiffer())->diff("same\n", "same\n"));
    }

    public function testDetectsAddedLine(): void
    {
        $diff = (new FileDiffer())->diff("a\nb\nc", "a\nb\nb2\nc");
        $this->assertStringContainsString('+b2', $diff);
        $this->assertStringContainsString('--- before', $diff);
        $this->assertStringContainsString('+++ after', $diff);
    }

    public function testDetectsRemovedLine(): void
    {
        $diff = (new FileDiffer())->diff("a\nb\nc", "a\nc");
        $this->assertStringContainsString('-b', $diff);
    }

    public function testDetectsReplacedLine(): void
    {
        $diff = (new FileDiffer())->diff("a\nold\nc", "a\nnew\nc");
        $this->assertStringContainsString('-old', $diff);
        $this->assertStringContainsString('+new', $diff);
    }

    public function testFromEmptyToContent(): void
    {
        $diff = (new FileDiffer())->diff('', "first\nsecond\n");
        $this->assertStringContainsString('+first', $diff);
        $this->assertStringContainsString('+second', $diff);
    }
}
