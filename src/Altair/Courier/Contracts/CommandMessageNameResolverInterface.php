<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

interface CommandMessageNameResolverInterface
{
    /**
     * Resolves the name identifier of the message instance.
     *
     * @param CommandMessageInterface $message
     *
     * @return string
     */
    public function resolve(CommandMessageInterface $message): string;
}
