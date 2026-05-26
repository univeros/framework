<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Traits;

trait SerializeAwareTrait
{
    /**
     * @see ArrayableInterface::toArray()
     */
    abstract public function toArray(): array;

    /**
     *
     * @see AttributesAwareTrait::withData()
     * @return mixed
     */
    abstract public function withData(array $data);

    /**
     * @inheritDoc
     */
    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    /**
     * @see AttributesAwareTrait::withData($data)
     *
     * @inheritDoc
     */
    public function unserialize($data): void
    {
        foreach (unserialize($data, ['allowed_classes' => true]) as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
