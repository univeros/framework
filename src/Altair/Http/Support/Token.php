<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenInterface;
use Override;

class Token implements TokenInterface
{
    /**
     * Token constructor.
     *
     * @param $token
     * @param string $token
     */
    public function __construct(private $token, private array $metadata) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMetadata(?string $key = null)
    {
        return null !== $key
            ? $this->metadata[$key] ?? null
            : null;
    }
}
