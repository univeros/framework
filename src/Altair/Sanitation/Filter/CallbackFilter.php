<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class CallbackFilter extends AbstractFilter
{
    /**
     * @var callable
     */
    protected $callable;

    /**
     * CallbackRule constructor.
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        return call_user_func($this->callable, $value);
    }
}
