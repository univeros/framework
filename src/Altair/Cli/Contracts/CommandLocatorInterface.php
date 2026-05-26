<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Contracts;

interface CommandLocatorInterface
{
    /**
     * Scan the given paths and return the fully-qualified class names of
     * every class decorated with the framework's #[Command] attribute.
     *
     * @param  list<string>             $paths
     * @return iterable<class-string>
     */
    public function scan(array $paths): iterable;
}
