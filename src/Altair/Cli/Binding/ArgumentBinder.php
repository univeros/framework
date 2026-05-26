<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Binding;

use Altair\Cli\Attribute\Argument;
use ReflectionParameter;
use Symfony\Component\Console\Input\InputArgument;

class ArgumentBinder
{
    public function __construct(
        private readonly ParameterTypeInspector $inspector = new ParameterTypeInspector(),
    ) {}

    public function supports(ReflectionParameter $parameter): bool
    {
        return $parameter->getAttributes(Argument::class) !== [];
    }

    public function bind(ReflectionParameter $parameter): InputArgument
    {
        $attribute = $this->resolveAttribute($parameter);
        $name = $attribute->name ?? $parameter->getName();
        $hasDefault = $parameter->isDefaultValueAvailable();
        $isArray = $this->inspector->isArray($parameter);

        $mode = $hasDefault ? InputArgument::OPTIONAL : InputArgument::REQUIRED;
        if ($isArray) {
            $mode |= InputArgument::IS_ARRAY;
        }

        $default = $hasDefault ? $this->inspector->defaultForConsole($parameter) : null;
        if ($isArray && $default === null && $hasDefault) {
            $default = [];
        }

        return new InputArgument($name, $mode, $attribute->description, $default);
    }

    private function resolveAttribute(ReflectionParameter $parameter): Argument
    {
        $attributes = $parameter->getAttributes(Argument::class);
        if ($attributes === []) {
            return new Argument();
        }

        return $attributes[0]->newInstance();
    }
}
