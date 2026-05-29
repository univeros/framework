<?php

declare(strict_types=1);

namespace Altair\Tests\Data;

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Data\Traits\DateAttributeMutatorAwareTrait;
use Altair\Data\Traits\ImmutableAttributesAwareTrait;
use Altair\Data\Traits\JsonSerializableAwareTrait;
use Altair\Data\Traits\SerializeAwareTrait;

class Entity implements DataObjectInterface
{
    use ImmutableAttributesAwareTrait;
    use JsonSerializableAwareTrait;
    use DateAttributeMutatorAwareTrait;
    use SerializeAwareTrait;

    private $id;

    private $name;

    private $created_at;
}
