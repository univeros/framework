<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

use Altair\Container\Reflection\ClassMetadata;
use Altair\Container\Reflection\ParameterMetadata;
use ReflectionFunctionAbstract;

interface ReflectorInterface
{
    /**
     * Resolve the cached, render-agnostic metadata for a class (constructor
     * parameters, instantiability, class-level attributes).
     */
    public function classMetadata(string $class): ClassMetadata;

    /**
     * Extract parameter metadata for an arbitrary function/method (used by the
     * invoker). Not cached — callables are not a hot path.
     *
     * @return list<ParameterMetadata>
     */
    public function parametersOf(ReflectionFunctionAbstract $function): array;
}
