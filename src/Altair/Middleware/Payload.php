<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\JobInterface;
use JsonSerializable;

class Payload implements PayloadInterface, JsonSerializable
{
    /**
     * @var array|null
     */
    protected $attributes;

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
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name]?? $default;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function withAttribute($name, $value): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->attributes[$name] = $value;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    public function withAttributes(array $attributes): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->attributes = $attributes;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute($name): PayloadInterface
    {
        $cloned = clone $this;
        unset($cloned[$name]);

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return $this->attributes;
    }
}
