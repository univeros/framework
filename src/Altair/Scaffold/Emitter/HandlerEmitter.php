<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\QueueDispatchSpec;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Emits a handler stub for a queue: entry, decorated with
 * #[Altair\Messaging\Attribute\AsHandler] so the framework's discoverer
 * picks it up at boot.
 *
 * The body is intentionally a TODO to force the developer to wire real
 * behavior. Idempotency: the writer skips on re-runs unless --force is
 * passed.
 */
class HandlerEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(QueueDispatchSpec $queue): EmittedFile
    {
        $messageFqcn = $queue->message;
        $handlerFqcn = $this->naming->handlerFqcn($messageFqcn);
        $namespace = $this->namespaceOf($handlerFqcn);
        $shortName = $this->shortNameOf($handlerFqcn);
        $messageShort = $this->shortNameOf($messageFqcn);

        $header = PhpHeader::render($namespace);
        $body = <<<PHP
            use Altair\\Messaging\\Attribute\\AsHandler;
            use {$messageFqcn};
            use LogicException;

            /**
             * Generated handler for {$messageFqcn}.
             *
             * TODO: implement the side-effect this handler should produce.
             */
            #[AsHandler({$messageShort}::class)]
            final class {$shortName}
            {
                public function __invoke({$messageShort} \$message): void
                {
                    throw new LogicException('TODO: implement ' . self::class . '::__invoke().');
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->handlerPath($messageFqcn),
            contents: $header . $body,
            kind: EmittedFileKind::Handler,
        );
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    private function shortNameOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
