<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Output;

use Altair\Doctor\Exception\DoctorException;
use Altair\Doctor\Output\HumanRenderer;
use Altair\Doctor\Output\JsonRenderer;
use Altair\Doctor\Output\RendererRegistry;
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Altair\Doctor\Result\Report;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonRenderer::class)]
#[CoversClass(HumanRenderer::class)]
#[CoversClass(RendererRegistry::class)]
class RenderersTest extends TestCase
{
    public function testJsonIsDeterministicAndOrdered(): void
    {
        $report = new Report([
            CheckResult::ok('php_version', 'PHP 8.4 satisfies >=8.3'),
            CheckResult::warn('cs_clean', 'dirty', 'Run `composer cs:fix`.', AgentAction::runCommand('composer cs:fix')),
        ], 0);

        $renderer = new JsonRenderer();
        $first = $renderer->render($report);

        $this->assertSame($first, $renderer->render($report), 'same report must render byte-identically');

        /** @var array{status: string, duration_ms: int, checks: list<array<string, mixed>>} $decoded */
        $decoded = json_decode(trim($first), true);
        $this->assertSame(['status', 'duration_ms', 'checks'], array_keys($decoded));
        $this->assertSame(['name', 'status', 'detail'], array_keys($decoded['checks'][0]));
        $this->assertSame('warn', $decoded['status']);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $first, 'no timestamps in output');
    }

    public function testHumanRendersStatusFixAndCommandSnippet(): void
    {
        $report = new Report([
            CheckResult::ok('php_version', 'fine'),
            CheckResult::warn('cs_clean', 'dirty', 'Run `composer cs:fix`.', AgentAction::runCommand('composer cs:fix')),
        ], 5);

        $out = (new HumanRenderer())->render($report);

        $this->assertStringContainsString('[ok', $out);
        $this->assertStringContainsString('[warn', $out);
        $this->assertStringContainsString('fix: Run `composer cs:fix`.', $out);
        $this->assertStringContainsString('$ composer cs:fix', $out);
        $this->assertStringContainsString('WARN — 2 checks in 5ms', $out);
    }

    public function testRegistryResolvesAndRejectsUnknownFormat(): void
    {
        $registry = RendererRegistry::default();

        $this->assertInstanceOf(JsonRenderer::class, $registry->get('json'));
        $this->assertInstanceOf(HumanRenderer::class, $registry->get('human'));
        $this->assertSame(['human', 'json'], $registry->available());

        $this->expectException(DoctorException::class);
        $registry->get('xml');
    }
}
