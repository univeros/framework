<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Emits a domain service stub. Body is intentionally a TODO so the developer
 * is forced to implement business logic. Idempotency: skipped on re-runs
 * unless --force is passed.
 */
class DomainStubEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        $fqcn = $spec->domain->class;
        $namespace = $this->namespaceOf($fqcn);
        $shortName = $this->shortNameOf($fqcn);
        $inputFqcn = $this->naming->inputFqcn($spec);
        $invocation = $spec->domain->invocation;

        $inputShort = $this->shortNameOf($inputFqcn);
        $header = PhpHeader::render($namespace);
        $body = <<<PHP
            use Altair\\Http\\Contracts\\PayloadInterface;
            use {$inputFqcn};
            use LogicException;

            /**
             * Generated domain stub.
             *
             * TODO: implement {$spec->endpoint->method} {$spec->endpoint->path} business logic.
             */
            final class {$shortName}
            {
                public function {$invocation}({$inputShort} \$input, PayloadInterface \$payload): PayloadInterface
                {
                    throw new LogicException('TODO: implement ' . self::class . '::{$invocation}().');
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->domainPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::DomainStub,
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
