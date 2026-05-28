<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Fixtures;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

#[Entity(table: 'widgets')]
class SampleEntity
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'string(150)', nullable: false)]
    public string $name;

    #[Column(type: 'integer', nullable: true)]
    public ?int $weight = null;
}
