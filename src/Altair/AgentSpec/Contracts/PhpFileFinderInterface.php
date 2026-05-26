<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Contracts;

interface PhpFileFinderInterface
{
    /**
     * Recursively yield absolute paths to every .php file under $directory.
     *
     * @return iterable<string>
     */
    public function find(string $directory): iterable;
}
