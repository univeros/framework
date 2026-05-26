<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Binding;

use Altair\Cli\Attribute\Option;
use Altair\Cli\Binding\OptionBinder;
use Altair\Tests\Cli\Fixture\Role;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionParameter;
use Symfony\Component\Console\Input\InputOption;

class OptionBinderTest extends TestCase
{
    public function testBoolBecomesFlag(): void
    {
        $binder = new OptionBinder();
        $parameter = $this->parameter(
            static fn (
                #[Option(description: 'Silent')]
                bool $silent = false,
            ): int => 0,
        );

        $option = $binder->bind($parameter);
        $this->assertSame('silent', $option->getName());
        $this->assertFalse($option->acceptValue());
        $this->assertSame('Silent', $option->getDescription());
    }

    public function testStringValueOption(): void
    {
        $binder = new OptionBinder();
        $parameter = $this->parameter(
            static fn (
                #[Option(short: 'p')]
                ?string $password = null,
            ): int => 0,
        );

        $option = $binder->bind($parameter);
        $this->assertSame('password', $option->getName());
        $this->assertSame('p', $option->getShortcut());
        $this->assertTrue($option->isValueRequired());
        $this->assertNull($option->getDefault());
    }

    public function testEnumDefaultUsesBackingValue(): void
    {
        $binder = new OptionBinder();
        $parameter = $this->parameter(
            static fn (
                #[Option]
                Role $role = Role::Member,
            ): int => 0,
        );

        $option = $binder->bind($parameter);
        $this->assertSame('role', $option->getName());
        $this->assertSame(Role::Member->value, $option->getDefault());
    }

    public function testArrayOption(): void
    {
        $binder = new OptionBinder();
        $parameter = $this->parameter(
            static fn (
                #[Option]
                array $tag = [],
            ): int => 0,
        );

        $option = $binder->bind($parameter);
        $this->assertTrue($option->isArray());
        $this->assertSame([], $option->getDefault());
    }

    public function testCamelCaseNameIsKebabCased(): void
    {
        $binder = new OptionBinder();
        $parameter = $this->parameter(
            static fn (
                #[Option]
                ?string $maxRetries = null,
            ): int => 0,
        );

        $option = $binder->bind($parameter);
        $this->assertSame('max-retries', $option->getName());
    }

    public function testNameOverride(): void
    {
        $binder = new OptionBinder();
        $parameter = $this->parameter(
            static fn (
                #[Option(name: 'output-dir')]
                ?string $out = null,
            ): int => 0,
        );

        $option = $binder->bind($parameter);
        $this->assertSame('output-dir', $option->getName());
    }

    private function parameter(callable $callable): ReflectionParameter
    {
        return (new ReflectionFunction($callable))->getParameters()[0];
    }
}
