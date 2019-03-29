<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

interface ExecutableBuilderInterface extends BuilderInterface
{
    /**
     * Checks whether callable or method string is executable
     *
     * @param mixed $executable
     *
     * @return bool
     */
    public function isExecutable($executable): bool;
}
