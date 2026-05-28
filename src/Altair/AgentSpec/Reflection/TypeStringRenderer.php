<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Reflection;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Renders a ReflectionType as a short, human-readable type string
 * (`ServerRequestInterface|null`, `Foo&Bar`, `void`).
 */
class TypeStringRenderer
{
    public function render(?ReflectionType $type): string
    {
        if (!$type instanceof ReflectionType) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->renderNamed($type);
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = array_map($this->render(...), $type->getTypes());

            return implode('|', $parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = array_map($this->render(...), $type->getTypes());

            return implode('&', $parts);
        }

        return (string) $type;
    }

    public function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function renderNamed(ReflectionNamedType $type): string
    {
        $name = $type->getName();
        $short = $this->shortName($name);

        return $type->allowsNull() && $name !== 'mixed' && $name !== 'null'
            ? $short . '|null'
            : $short;
    }
}
