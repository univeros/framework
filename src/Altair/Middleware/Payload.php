<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware;

use Altair\Middleware\Contracts\PayloadInterface;
use JsonSerializable;

class Payload implements PayloadInterface, JsonSerializable
{
    protected array $attributes;

    /**
     * Payload constructor.
     *
     * @param array|null $attributes
     */
    public function __construct(array $attributes = null)
    {
        $this->attributes = $attributes?? [];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name]?? $default;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withAttribute($name, $value): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->attributes[$name] = $value;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withAttributes(array $attributes): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->attributes = $attributes;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withoutAttribute($name): PayloadInterface
    {
        $cloned = clone $this;
        unset($cloned[$name]);

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function jsonSerialize(): mixed
    {
        return $this->attributes;
    }
}
