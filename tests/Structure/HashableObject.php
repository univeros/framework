<?php

declare(strict_types=1);

namespace Altair\Tests\Structure;

use Altair\Structure\Contracts\HashableInterface;

/**
 *
 */
class HashableObject implements HashableInterface
{
    private $hash;

    public function __construct(private $value, $hash = null)
    {
        $this->hash = func_num_args() === 1 ? $this->value : $hash;
    }

    #[\Override]
    public function equals($obj): bool
    {
        return $obj->value === $this->value;
    }

    #[\Override]
    public function hash()
    {
        return $this->hash;
    }
}
