<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Support\Fixtures;

/** A union-typed constructor dependency — both members are graph edges. */
final readonly class UnionDepService
{
    public function __construct(
        public Collaborator|SampleListener $either,
    ) {}
}
