<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Schema;

use Altair\Container\Container;
use Cycle\Database\DatabaseProviderInterface;
use Override;

/**
 * A {@see SchemaProviderInterface} that compiles the runtime schema from the
 * host's entity directories PLUS every registered module's entity directories.
 *
 * Bind this in place of a bare {@see AttributeSchemaProvider} so a module's
 * entities join the ORM the moment the module is registered — no host edit. The
 * compiled schema is memoized (the underlying provider memoizes), so the
 * tokenizer/reflection work runs at most once per process.
 */
final class ModuleAwareSchemaProvider implements SchemaProviderInterface
{
    private ?AttributeSchemaProvider $delegate = null;

    /**
     * @param list<string> $baseDirectories the host's own entity directories
     */
    public function __construct(
        private readonly DatabaseProviderInterface $databases,
        private readonly Container $container,
        private readonly array $baseDirectories = [],
    ) {}

    #[Override]
    public function schema(): array
    {
        return ($this->delegate ??= new AttributeSchemaProvider(
            $this->databases,
            ModuleEntityDirectories::collect($this->container, $this->baseDirectories),
        ))->schema();
    }
}
