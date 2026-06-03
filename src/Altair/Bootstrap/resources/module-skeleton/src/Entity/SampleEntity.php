<?php

declare(strict_types=1);

namespace VendorModule\Entity;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

/**
 * A sample persisted entity. Because this directory is returned from
 * {@see \VendorModule\Module::entityDirectories()}, it joins the host's ORM
 * schema automatically once the module is registered.
 */
#[Entity(table: 'module_samples')]
class SampleEntity
{
    #[Column(type: 'primary')]
    public int $id = 0;

    #[Column(type: 'string')]
    public string $name = '';
}
