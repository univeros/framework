<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Model;

final readonly class ContractEntry
{
    /**
     * @param list<MethodSignature> $methods
     * @param list<string>          $extends
     * @param list<string>          $constants Short names of public class constants exposed by the interface.
     */
    public function __construct(
        public string $fullyQualifiedName,
        public string $shortName,
        public array $methods,
        public array $extends,
        public array $constants,
    ) {}
}
