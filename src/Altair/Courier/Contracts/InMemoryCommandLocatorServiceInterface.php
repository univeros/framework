<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

use Altair\Courier\Support\MessageCommandMap;

interface InMemoryCommandLocatorServiceInterface extends CommandLocatorServiceInterface
{
    /**
     * Returns a new instance of the locator with the specified mapping.
     *
     * @param MessageCommandMap $map
     *
     * @return InMemoryCommandLocatorServiceInterface
     */
    public function withMap(MessageCommandMap $map): InMemoryCommandLocatorServiceInterface;

    /**
     * Adds a new message to command mapping.
     *
     * @param string $messageName
     * @param string $commandName
     *
     * @return InMemoryCommandLocatorServiceInterface
     */
    public function add(string $messageName, string $commandName): InMemoryCommandLocatorServiceInterface;
}
