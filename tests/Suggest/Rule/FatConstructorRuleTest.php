<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Rule;

use Altair\Suggest\Rule\FatConstructorRule;
use Altair\Suggest\Snapshot\BindingNode;
use Altair\Suggest\Snapshot\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FatConstructorRule::class)]
class FatConstructorRuleTest extends TestCase
{
    public function testFlagsBindingOverDefaultThreshold(): void
    {
        $snapshot = new Snapshot(bindings: [$this->binding('App\\Fat', 6), $this->binding('App\\Lean', 5)]);

        $suggestions = (new FatConstructorRule())->analyse($snapshot);

        $this->assertCount(1, $suggestions);
        $this->assertSame('App\\Fat', $suggestions[0]->subject);
        $this->assertStringContainsString('6 constructor dependencies', $suggestions[0]->message);
    }

    public function testRespectsCustomThreshold(): void
    {
        $snapshot = new Snapshot(bindings: [$this->binding('App\\Svc', 3)]);

        $this->assertCount(1, (new FatConstructorRule(2))->analyse($snapshot));
        $this->assertSame([], (new FatConstructorRule(3))->analyse($snapshot));
    }

    private function binding(string $id, int $deps): BindingNode
    {
        $dependencies = array_map(static fn(int $i): string => 'Dep' . $i, range(1, $deps));

        return new BindingNode($id, 'share', $id, true, $dependencies);
    }
}
