<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Support;

use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Contracts\KeyInterface;
use Override;
use Stringable;

abstract class AbstractKey implements KeyInterface, Stringable
{
    /**
     * @var string
     */
    protected $algorithm = EncrypterInterface::HASH_SHA256_ALGORITHM;

    /**
     * AbstractKey constructor.
     *
     * @param string|null $salt
     *
     */
    public function __construct(protected string $key, protected ?string $salt = null, protected int $length = 0) {}

    #[Override]
    public function __toString(): string
    {
        return $this->derive();
    }
}
