<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Traits;

trait ResolverAwareTrait
{
    /**
     * @var callable(string): object
     */
    protected $resolver;

    /**
     * Resolve a fully-qualified class name into an object.
     */
    protected function resolve(string $spec): object
    {
        return ($this->resolver)($spec);
    }
}
