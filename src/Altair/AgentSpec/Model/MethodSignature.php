<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Model;

final readonly class MethodSignature
{
    /**
     * @param list<string> $parameterTypes Short parameter type names (e.g. "ServerRequestInterface, RequestHandlerInterface").
     */
    public function __construct(
        public string $name,
        public array $parameterTypes,
        public string $returnType,
    ) {}

    public function renderParameters(): string
    {
        return implode(', ', $this->parameterTypes);
    }
}
