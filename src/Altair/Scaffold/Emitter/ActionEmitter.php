<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\IdempotencySpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Emits an Action class that wires together input, domain, and responder.
 *
 * The output extends `Altair\Http\Base\Action`; configuration is done in
 * the constructor body so the action stays self-contained and discoverable.
 *
 * When the spec carries an `idempotency:` block, the emitted class
 * exposes a static `idempotency()` accessor with the configured TTL,
 * scope, and mode so the host application's
 * `Altair\Idempotency\Middleware\IdempotencyKeyMiddleware` can be
 * configured from spec metadata.
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
        $idempotencyAccessor = $this->renderIdempotencyAccessor($spec->idempotency);

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
            {$idempotencyAccessor}}

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

    /**
     * Renders the static `idempotency()` accessor that surfaces the
     * spec's idempotency policy to the host application's middleware
     * stack. Returns an empty string when the spec omits the block so
     * pre-existing scaffolds stay byte-for-byte identical.
     */
    private function renderIdempotencyAccessor(?IdempotencySpec $idempotency): string
    {
        if (!$idempotency instanceof IdempotencySpec) {
            return '';
        }

        $ttl = var_export($idempotency->ttl, true);
        $scope = var_export($idempotency->scope, true);
        $mode = var_export($idempotency->mode, true);

        return <<<PHP

                /**
                 * Idempotency-Key policy for this endpoint. Consumed by the host
                 * application's IdempotencyKeyMiddleware via IdempotencyConfiguration.
                 *
                 * @return array{ttl: string, scope: string, mode: string}
                 */
                public static function idempotency(): array
                {
                    return ['ttl' => {$ttl}, 'scope' => {$scope}, 'mode' => {$mode}];
                }

            PHP;
    }
}
