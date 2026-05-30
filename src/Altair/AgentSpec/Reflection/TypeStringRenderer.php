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
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Renders a ReflectionType as a short, human-readable type string
 * (`ServerRequestInterface|null`, `Foo&Bar`, `void`).
 *
 * Pass the owning ReflectionMethod when rendering parameter or return
 * types so the `self` / `static` / `parent` keywords resolve to the
 * declaring class. `ReflectionNamedType::getName()` returns either the
 * keyword or the resolved class name depending on PHP minor version,
 * which used to make manifests non-deterministic across PHP releases
 * (see #158).
 */
class TypeStringRenderer
{
    private const array RELATIVE_KEYWORDS = ['self', 'static', 'parent'];

    public function render(?ReflectionType $type, ?ReflectionMethod $context = null): string
    {
        if (!$type instanceof ReflectionType) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->renderNamed($type, $context);
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = array_map(fn(ReflectionType $part): string => $this->render($part, $context), $type->getTypes());

            return implode('|', $parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = array_map(fn(ReflectionType $part): string => $this->render($part, $context), $type->getTypes());

            return implode('&', $parts);
        }

        return (string) $type;
    }

    public function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function renderNamed(ReflectionNamedType $type, ?ReflectionMethod $context): string
    {
        $name = $this->canonicalName($type->getName(), $context);
        $short = $this->shortName($name);

        return $type->allowsNull() && $name !== 'mixed' && $name !== 'null'
            ? $short . '|null'
            : $short;
    }

    /**
     * Resolve `self` / `static` / `parent` to a stable class name so the
     * rendered string does not depend on which PHP minor release reflected it.
     */
    private function canonicalName(string $name, ?ReflectionMethod $context): string
    {
        if (!$context instanceof ReflectionMethod || !\in_array($name, self::RELATIVE_KEYWORDS, true)) {
            return $name;
        }

        if ($name === 'parent') {
            $parent = $context->getDeclaringClass()->getParentClass();

            return $parent !== false ? $parent->getName() : $name;
        }

        return $context->getDeclaringClass()->getName();
    }
}
