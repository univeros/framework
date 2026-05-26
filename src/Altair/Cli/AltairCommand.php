<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli;

use Altair\Cli\Attribute\Argument as ArgumentAttribute;
use Altair\Cli\Attribute\Command as CommandAttribute;
use Altair\Cli\Attribute\Option as OptionAttribute;
use Altair\Cli\Binding\ArgumentBinder;
use Altair\Cli\Binding\OptionBinder;
use Altair\Cli\Binding\ParameterTypeInspector;
use Altair\Cli\Binding\ValueCoercer;
use Altair\Cli\Exception\InvalidCommandException;
use Altair\Cli\Exception\ValueCoercionException;
use Altair\Container\Container;
use Override;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException as SymfonyInvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bridges a user-authored, attribute-decorated invokable class to
 * Symfony Console's Command surface. Each AltairCommand wraps exactly
 * one user class and delegates execution to its __invoke method.
 */
class AltairCommand extends SymfonyCommand
{
    private readonly CommandAttribute $metadata;

    private readonly ReflectionMethod $invoker;

    /** @var list<ReflectionParameter> */
    private readonly array $parameters;

    /**
     * @param class-string $commandClass
     */
    public function __construct(
        private readonly string $commandClass,
        private readonly Container $container,
        private readonly ArgumentBinder $argumentBinder = new ArgumentBinder(),
        private readonly OptionBinder $optionBinder = new OptionBinder(),
        private readonly ValueCoercer $coercer = new ValueCoercer(),
        private readonly ParameterTypeInspector $inspector = new ParameterTypeInspector(),
    ) {
        $this->metadata = $this->resolveMetadata($commandClass);
        $this->invoker = $this->resolveInvoker($commandClass);
        $this->parameters = $this->invoker->getParameters();

        parent::__construct($this->metadata->name);
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription($this->metadata->description)
            ->setAliases($this->metadata->aliases)
            ->setHidden($this->metadata->hidden);

        if ($this->metadata->help !== '') {
            $this->setHelp($this->metadata->help);
        }

        foreach ($this->parameters as $parameter) {
            if ($this->argumentBinder->supports($parameter)) {
                $this->getDefinition()->addArgument($this->argumentBinder->bind($parameter));
                continue;
            }

            if ($this->optionBinder->supports($parameter)) {
                $this->getDefinition()->addOption($this->optionBinder->bind($parameter));
                continue;
            }

            throw new InvalidCommandException(
                \sprintf(
                    "Parameter '%s' of %s::__invoke() is missing an Argument or Option attribute.",
                    $parameter->getName(),
                    $this->commandClass,
                ),
            );
        }
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $arguments = $this->resolveArguments($input);
        } catch (ValueCoercionException $e) {
            throw new SymfonyInvalidArgumentException($e->getMessage(), 0, $e);
        }

        $instance = $this->container->make($this->commandClass);
        $result = $this->invoker->invoke($instance, ...$arguments);

        if ($result === null) {
            return self::SUCCESS;
        }

        if (!\is_int($result)) {
            throw new InvalidCommandException(
                \sprintf(
                    "%s::__invoke() must return int or void; got %s.",
                    $this->commandClass,
                    get_debug_type($result),
                ),
            );
        }

        return $result;
    }

    /**
     * @return list<mixed>
     */
    private function resolveArguments(InputInterface $input): array
    {
        $resolved = [];
        foreach ($this->parameters as $parameter) {
            $resolved[] = $this->resolveParameter($parameter, $input);
        }

        return $resolved;
    }

    private function resolveParameter(ReflectionParameter $parameter, InputInterface $input): mixed
    {
        $type = $this->inspector->namedType($parameter);
        $argumentAttribute = $parameter->getAttributes(ArgumentAttribute::class);

        if ($argumentAttribute !== []) {
            $attribute = $argumentAttribute[0]->newInstance();
            $name = $attribute->name ?? $parameter->getName();
            $value = $input->getArgument($name);

            return $this->coercer->coerce($value, $type, $parameter->getName());
        }

        $optionAttribute = $parameter->getAttributes(OptionAttribute::class);
        if ($optionAttribute === []) {
            throw new InvalidCommandException(
                \sprintf("Parameter '%s' has no Argument or Option attribute.", $parameter->getName()),
            );
        }

        $attribute = $optionAttribute[0]->newInstance();
        $name = $attribute->name ?? $this->inspector->kebabCase($parameter->getName());
        $value = $input->getOption($name);

        return $this->coercer->coerce($value, $type, $parameter->getName());
    }

    /**
     * @param class-string $commandClass
     */
    private function resolveMetadata(string $commandClass): CommandAttribute
    {
        if (!class_exists($commandClass)) {
            throw new InvalidCommandException(
                \sprintf("Command class '%s' does not exist.", $commandClass),
            );
        }

        $reflection = new ReflectionClass($commandClass);
        $attributes = $reflection->getAttributes(CommandAttribute::class);

        if ($attributes === []) {
            throw new InvalidCommandException(
                \sprintf("Class '%s' is missing the Command attribute.", $commandClass),
            );
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param class-string $commandClass
     */
    private function resolveInvoker(string $commandClass): ReflectionMethod
    {
        if (!method_exists($commandClass, '__invoke')) {
            throw new InvalidCommandException(
                \sprintf("Class '%s' must define an __invoke() method.", $commandClass),
            );
        }

        return new ReflectionMethod($commandClass, '__invoke');
    }
}
