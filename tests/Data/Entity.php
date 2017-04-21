<?php

namespace Altair\Tests\Data;

use Altair\Data\Contracts\EntityInterface;
use Altair\Data\Traits\DateAttributeMutatorAwareTrait;
use Altair\Data\Traits\ImmutableAttributesAwareTrait;
use Altair\Data\Traits\JsonSerializableAwareTrait;
use Altair\Data\Traits\SerializeAwareTrait;

class Entity implements EntityInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use DateAttributeMutatorAwareTrait;
    use SerializeAwareTrait;

    private $id;
    private $name;
    private $created_at;
}
