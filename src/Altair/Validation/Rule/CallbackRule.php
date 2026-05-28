<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Override;

class CallbackRule extends AbstractRule
{
    /**
     * @var callable
     */
    protected $callable;

    /**
     * CallbackRule constructor.
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function assert(mixed $value): bool
    {
        return \call_user_func($this->callable, $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage(mixed $value): string
    {
        return \sprintf('"%s" is not a valid value.', $value);
    }
}
