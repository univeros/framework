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
 * Emits an Action class that wires together input, domain, and responder.
 *
 * The output extends `Altair\Http\Base\Action`; configuration is done in
 * the constructor body so the action stays self-contained and discoverable.
 */
class ActionEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        $shortName = $this->naming->actionShortName($spec);
        $namespace = $this->namespaceOf($this->naming->actionFqcn($spec));
        $inputFqcn = $this->naming->inputFqcn($spec);
        $responderFqcn = $this->naming->responderFqcn($spec);
        $domainFqcn = $spec->domain->class;

        $header = PhpHeader::render($namespace);
        $body = <<<PHP
            use Altair\\Http\\Base\\Action;
            use {$inputFqcn};
            use {$responderFqcn};
            use {$domainFqcn};

            /**
             * Generated action for {$spec->endpoint->method} {$spec->endpoint->path}.
             */
            final class {$shortName} extends Action
            {
                public function __construct()
                {
                    parent::__construct(
                        domain: \\{$domainFqcn}::class,
                        responder: \\{$responderFqcn}::class,
                        input: \\{$inputFqcn}::class,
                    );
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->actionPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::Action,
        );
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }
}
