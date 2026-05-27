<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Resolver;

use Altair\TestReporter\Resolver\SourceUnderTestResolver;
use Altair\Tests\TestReporter\Fixtures\ExampleHttpCacheTest;
use Altair\Tests\TestReporter\Fixtures\ExampleNoCoversTest;
use Altair\Tests\TestReporter\Fixtures\LegacyCoversAnnotationTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceUnderTestResolver::class)]
class SourceUnderTestResolverTest extends TestCase
{
    public function testResolvesViaCoversClassAttribute(): void
    {
        $resolver = new SourceUnderTestResolver(\dirname(__DIR__, 3));
        $sources = $resolver->resolve(ExampleHttpCacheTest::class, 'testIsCacheableReturnsTrueWithMaxAge');

        $this->assertNotSame([], $sources);
        $this->assertStringContainsString('ExampleHttpCache.php', $sources[0]->file);
        $this->assertSame('isCacheable', $sources[0]->method);
    }

    public function testResolvesViaLegacyCoversAnnotation(): void
    {
        $resolver = new SourceUnderTestResolver(\dirname(__DIR__, 3));
        $sources = $resolver->resolve(LegacyCoversAnnotationTest::class, 'testAnything');

        $this->assertNotSame([], $sources);
        $this->assertStringContainsString('ExampleHttpCache.php', $sources[0]->file);
    }

    public function testResolvesViaNamespaceHeuristic(): void
    {
        $resolver = new SourceUnderTestResolver(\dirname(__DIR__, 3));
        $sources = $resolver->resolve(ExampleNoCoversTest::class, 'testCompute');

        $this->assertNotSame([], $sources);
        $this->assertStringContainsString('ExampleNoCovers.php', $sources[0]->file);
        $this->assertSame('compute', $sources[0]->method);
    }

    public function testReturnsEmptyForUnknownClass(): void
    {
        $resolver = new SourceUnderTestResolver(\dirname(__DIR__, 3));
        $sources = $resolver->resolve('Some\\Class\\That\\Does\\Not\\Exist', 'testX');
        $this->assertSame([], $sources);
    }
}
