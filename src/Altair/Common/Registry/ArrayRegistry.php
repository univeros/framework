<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Common\Registry;

use Altair\Common\Contracts\RegistryInterface;
use Altair\Common\Support\Arr;

class ArrayRegistry implements RegistryInterface
{
    protected $data;

    /**
     * ArrayRegistry constructor.
     *
     * @param array|null $data
     */
    public function __construct(array $data = null)
    {
        $this->data = $data ?? [];
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        return Arr::getValue($this->data, $key, $default);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }
}
