<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Output;

use Altair\Suggest\Exception\SuggestException;
use Altair\Suggest\Output\HumanRenderer;
use Altair\Suggest\Output\JsonRenderer;
use Altair\Suggest\Output\RendererRegistry;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Result\SuggestionReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HumanRenderer::class)]
#[CoversClass(JsonRenderer::class)]
#[CoversClass(RendererRegistry::class)]
class RenderersTest extends TestCase
{
    public function testHumanRendererEmptyReport(): void
    {
        $output = (new HumanRenderer())->render(new SuggestionReport([], 0));

        $this->assertStringContainsString('No suggestions', $output);
    }

    public function testHumanRendererListsSuggestionsWithFix(): void
    {
        $report = new SuggestionReport([
            new Suggestion('dead_event', Severity::Warning, 'x', 'no listeners', 'wire a listener'),
        ], 3);

        $output = (new HumanRenderer())->render($report);

        $this->assertStringContainsString('[warning] dead_event — no listeners', $output);
        $this->assertStringContainsString('fix: wire a listener', $output);
        $this->assertStringContainsString('1 suggestion(s) — 1 warning, 0 info — in 3ms', $output);
    }

    public function testJsonRendererIsDeterministicAndValid(): void
    {
        $report = new SuggestionReport([new Suggestion('a', Severity::Info, 's', 'm')], 5);

        $first = (new JsonRenderer())->render($report);
        $second = (new JsonRenderer())->render($report);

        $this->assertSame($first, $second);
        $this->assertJson(trim($first));
        $this->assertStringContainsString('"count": 1', $first);
    }

    public function testRegistryResolvesKnownFormats(): void
    {
        $registry = RendererRegistry::default();

        $this->assertInstanceOf(HumanRenderer::class, $registry->get('human'));
        $this->assertInstanceOf(JsonRenderer::class, $registry->get('json'));
        $this->assertSame(['human', 'json'], $registry->available());
    }

    public function testRegistryRejectsUnknownFormat(): void
    {
        $this->expectException(SuggestException::class);
        RendererRegistry::default()->get('xml');
    }
}
