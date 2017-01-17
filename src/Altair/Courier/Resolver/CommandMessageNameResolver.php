<?php
namespace Altair\Courier\Resolver;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMessageNameResolverInterface;

class CommandMessageNameResolver implements CommandMessageNameResolverInterface
{
    /**
     * @inheritdoc
     */
    public function resolve(CommandMessageInterface $message): string
    {
        return $message->getName();
    }
}
