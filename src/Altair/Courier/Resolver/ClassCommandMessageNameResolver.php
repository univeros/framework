<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Resolver;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMessageNameResolverInterface;

class ClassCommandMessageNameResolver implements CommandMessageNameResolverInterface
{
    /**
     * @inheritdoc
     */
    public function resolve(CommandMessageInterface $message): string
    {
        return get_class($message);
    }
}
