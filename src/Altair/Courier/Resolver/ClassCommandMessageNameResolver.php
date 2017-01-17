<?php
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
