<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Schema\Fixture\ModuleEntities;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

/**
 * Stands in for an entity shipped inside a module package. Lives in its own
 * directory so {@see \Altair\Persistence\Schema\ModuleAwareSchemaProviderTest}
 * can point a module's `entityDirectories()` at exactly this folder.
 */
#[Entity(table: 'module_accounts')]
class Account
{
    #[Column(type: 'primary')]
    public int $id = 0;

    #[Column(type: 'string')]
    public string $handle = '';
}
