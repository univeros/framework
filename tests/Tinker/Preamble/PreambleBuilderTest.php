<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Preamble;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Tinker\Preamble\PreambleBuilder;
use Altair\Tinker\Repl\ReplContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreambleBuilder::class)]
class PreambleBuilderTest extends TestCase
{
    public function testDegradesGracefullyWithoutInspectors(): void
    {
        $context = (new ReplContext())->withScopeVariable('container', new Container());

        $banner = (new PreambleBuilder())->build($context);

        $this->assertStringContainsString('Altair Tinker', $banner);
        $this->assertStringContainsString('In scope:', $banner);
        $this->assertStringContainsString('$container', $banner);
        $this->assertMatchesRegularExpression('/bindings\s+—/u', $banner);
        $this->assertStringContainsString('IntrospectionConfiguration', $banner);
    }

    public function testShowsBindingCountWhenContainerInspectorPresent(): void
    {
        $context = (new ReplContext())->withScopeVariable('container', new Container());
        $builder = new PreambleBuilder(new ContainerInspector(new Container()));

        $banner = $builder->build($context);

        $this->assertMatchesRegularExpression('/bindings\s+\d+/', $banner);
        $this->assertStringNotContainsString('IntrospectionConfiguration', $banner);
    }
}
