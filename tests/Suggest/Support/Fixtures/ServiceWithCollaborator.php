<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Support\Fixtures;

/** Has one object dependency and one scalar — the factory keeps only the object edge. */
final readonly class ServiceWithCollaborator
{
    public function __construct(
        public Collaborator $collaborator,
        public string $name,
    ) {}
}
