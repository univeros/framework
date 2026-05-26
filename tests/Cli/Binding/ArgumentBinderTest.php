<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Binding;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Binding\ArgumentBinder;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionParameter;
use Symfony\Component\Console\Input\InputArgument;

class ArgumentBinderTest extends TestCase
{
    public function testRequiredWhenNoDefault(): void
    {
        $binder = new ArgumentBinder();
        $parameter = $this->parameter(
            static fn (
                #[Argument(description: 'The email')]
                string $email,
            ): int => 0,
        );

        $this->assertTrue($binder->supports($parameter));

        $argument = $binder->bind($parameter);
        $this->assertSame('email', $argument->getName());
        $this->assertTrue($argument->isRequired());
        $this->assertFalse($argument->isArray());
        $this->assertSame('The email', $argument->getDescription());
    }

    public function testOptionalWhenDefaultProvided(): void
    {
        $binder = new ArgumentBinder();
        $parameter = $this->parameter(
            static fn (
                #[Argument]
                string $env = 'production',
            ): int => 0,
        );

        $argument = $binder->bind($parameter);
        $this->assertFalse($argument->isRequired());
        $this->assertSame('production', $argument->getDefault());
    }

    public function testArrayArgument(): void
    {
        $binder = new ArgumentBinder();
        $parameter = $this->parameter(
            static fn (
                #[Argument(description: 'Files')]
                array $files = [],
            ): int => 0,
        );

        $argument = $binder->bind($parameter);
        $this->assertTrue($argument->isArray());
        $this->assertSame([], $argument->getDefault());
    }

    public function testNameOverride(): void
    {
        $binder = new ArgumentBinder();
        $parameter = $this->parameter(
            static fn (
                #[Argument(name: 'user-email')]
                string $email,
            ): int => 0,
        );

        $argument = $binder->bind($parameter);
        $this->assertSame('user-email', $argument->getName());
    }

    public function testDoesNotSupportUnattributedParameter(): void
    {
        $binder = new ArgumentBinder();
        $parameter = $this->parameter(static fn (string $email): int => 0);

        $this->assertFalse($binder->supports($parameter));
    }

    private function parameter(callable $callable): ReflectionParameter
    {
        return (new ReflectionFunction($callable))->getParameters()[0];
    }
}
