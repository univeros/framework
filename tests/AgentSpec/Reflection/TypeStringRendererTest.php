<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\AgentSpec\Reflection;

use Altair\AgentSpec\Reflection\TypeStringRenderer;
use Altair\Tests\AgentSpec\Reflection\Fixtures\SelfReturningInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(TypeStringRenderer::class)]
final class TypeStringRendererTest extends TestCase
{
    /**
     * `ReflectionNamedType::getName()` returns the literal keyword `"self"`
     * on some PHP minor releases and the resolved class name on others.
     * The renderer must canonicalise either form to the declaring class
     * short name so manifests stay byte-identical across PHP versions (#158).
     */
    public function testSelfReturnTypeIsResolvedToDeclaringClass(): void
    {
        $method = new ReflectionMethod(SelfReturningInterface::class, 'withSelf');

        $rendered = (new TypeStringRenderer())->render($method->getReturnType(), $method);

        $this->assertSame('SelfReturningInterface', $rendered);
    }

    public function testStaticReturnTypeIsResolvedToDeclaringClass(): void
    {
        $method = new ReflectionMethod(SelfReturningInterface::class, 'withStatic');

        $rendered = (new TypeStringRenderer())->render($method->getReturnType(), $method);

        $this->assertSame('SelfReturningInterface', $rendered);
    }

    public function testSelfParameterTypeIsResolvedToDeclaringClass(): void
    {
        $method = new ReflectionMethod(SelfReturningInterface::class, 'merge');
        $parameter = $method->getParameters()[0];

        $rendered = (new TypeStringRenderer())->render($parameter->getType(), $method);

        $this->assertSame('SelfReturningInterface', $rendered);
    }

    public function testNullableSelfReturnTypeIsResolvedAndKeepsNullSuffix(): void
    {
        $method = new ReflectionMethod(SelfReturningInterface::class, 'maybeSelf');

        $rendered = (new TypeStringRenderer())->render($method->getReturnType(), $method);

        $this->assertSame('SelfReturningInterface|null', $rendered);
    }

    public function testNamedTypesUnaffectedByContext(): void
    {
        $method = new ReflectionMethod(SelfReturningInterface::class, 'name');

        $rendered = (new TypeStringRenderer())->render($method->getReturnType(), $method);

        $this->assertSame('string', $rendered);
    }
}
