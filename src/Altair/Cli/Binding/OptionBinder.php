<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Binding;

use Altair\Cli\Attribute\Option;
use Altair\Cli\Exception\InvalidCommandException;
use ReflectionParameter;
use Symfony\Component\Console\Input\InputOption;

class OptionBinder
{
    public function __construct(
        private readonly ParameterTypeInspector $inspector = new ParameterTypeInspector(),
    ) {}

    public function supports(ReflectionParameter $parameter): bool
    {
        return $parameter->getAttributes(Option::class) !== [];
    }

    public function bind(ReflectionParameter $parameter): InputOption
    {
        $attribute = $this->resolveAttribute($parameter);
        $name = $attribute->name ?? $this->inspector->kebabCase($parameter->getName());
        $isBool = $this->inspector->isBool($parameter);
        $isArray = $this->inspector->isArray($parameter);

        if ($isBool) {
            $mode = InputOption::VALUE_NONE;
            $default = null;
        } else {
            $mode = InputOption::VALUE_REQUIRED;
            if ($isArray) {
                $mode |= InputOption::VALUE_IS_ARRAY;
            }

            $default = $parameter->isDefaultValueAvailable()
                ? $this->inspector->defaultForConsole($parameter)
                : null;
            if ($isArray && $default === null) {
                $default = [];
            }
        }

        return new InputOption(
            $name,
            $attribute->short,
            $mode,
            $attribute->description,
            $default,
        );
    }

    private function resolveAttribute(ReflectionParameter $parameter): Option
    {
        $attributes = $parameter->getAttributes(Option::class);
        if ($attributes === []) {
            throw new InvalidCommandException(
                \sprintf("Parameter '%s' is not marked as a CLI option.", $parameter->getName()),
            );
        }

        return $attributes[0]->newInstance();
    }
}
