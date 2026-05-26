<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\OutputResponseSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Emits a Responder that maps payload status -> JSON body shape.
 *
 * The generated class exposes `statuses()` so the linter (and tests) can
 * cross-check spec-declared statuses against what the responder actually
 * produces, without parsing match expressions.
 */
class ResponderEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        $shortName = $this->naming->responderShortName($spec);
        $namespace = $this->namespaceOf($this->naming->responderFqcn($spec));

        $header = PhpHeader::render($namespace);
        $statusList = array_map(static fn(OutputResponseSpec $o): int => $o->status, $spec->outputs);
        sort($statusList);
        $statusesPhp = implode(', ', $statusList);

        $body = <<<PHP
            use Altair\\Http\\Contracts\\PayloadInterface;
            use Altair\\Http\\Contracts\\ResponderInterface;
            use Laminas\\Diactoros\\Response\\JsonResponse;
            use Psr\\Http\\Message\\ResponseInterface;
            use Psr\\Http\\Message\\ServerRequestInterface;

            /**
             * Generated responder for {$spec->endpoint->method} {$spec->endpoint->path}.
             */
            final class {$shortName} implements ResponderInterface
            {
                public function __invoke(
                    ServerRequestInterface \$request,
                    ResponseInterface \$response,
                    PayloadInterface \$payload,
                ): ResponseInterface {
                    \$status = \$payload->getStatus() ?? 200;

                    return new JsonResponse(\$payload->getOutput(), \$status);
                }

                /**
                 * Statuses this responder is expected to produce, derived from the spec.
                 *
                 * @return list<int>
                 */
                public static function statuses(): array
                {
                    return [{$statusesPhp}];
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->responderPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::Responder,
        );
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }
}
