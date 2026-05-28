<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Snapshot;

/**
 * One container binding, enriched with the structural facts the rules need:
 * the constructor dependency types and the interfaces the target implements.
 *
 * `dependencies` and `interfaces` carry fully-qualified class names exactly
 * as reflection reports them (no leading backslash). Matching against `id`
 * is case-insensitive because the Container lower-cases its keys.
 */
final readonly class BindingNode
{
    /**
     * @param list<string> $dependencies constructor object-parameter types
     * @param list<string> $interfaces   interfaces the target implements
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $target,
        public bool $shared,
        public array $dependencies = [],
        public array $interfaces = [],
    ) {}

    public function implements(string $interface): bool
    {
        foreach ($this->interfaces as $candidate) {
            if (strcasecmp($candidate, $interface) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this binding answer to `$identifier` either by its registered id
     * or by its resolved target? Case-insensitive, leading-backslash-tolerant.
     */
    public function matches(string $identifier): bool
    {
        $needle = strtolower(ltrim($identifier, '\\'));

        return strtolower(ltrim($this->id, '\\')) === $needle
            || strtolower(ltrim($this->target, '\\')) === $needle;
    }
}
