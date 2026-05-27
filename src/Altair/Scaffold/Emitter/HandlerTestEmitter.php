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
 * Emits a golden-path PHPUnit test for the generated handler. The test
 * just asserts the handler is constructible and invokable with the
 * generated message — the developer fills in real expectations.
 */
class HandlerTestEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(QueueDispatchSpec $queue): EmittedFile
    {
        $messageFqcn = $queue->message;
        $handlerFqcn = $this->naming->handlerFqcn($messageFqcn);
        $testShort = $this->naming->handlerTestShortName($messageFqcn);
        $testNamespace = 'Tests\\Messages';

        $header = PhpHeader::render($testNamespace);
        $this->shortNameOf($messageFqcn);
        $handlerShort = $this->shortNameOf($handlerFqcn);

        $body = <<<PHP
            use {$handlerFqcn};
            use {$messageFqcn};
            use LogicException;
            use PHPUnit\\Framework\\TestCase;

            final class {$testShort} extends TestCase
            {
                public function testHandlerStubThrowsUntilImplemented(): void
                {
                    \$handler = new {$handlerShort}();
                    \$message = new \\{$messageFqcn}({$this->renderArgs($queue)});

                    \$this->expectException(LogicException::class);
                    \$handler(\$message);
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->handlerTestPath($messageFqcn),
            contents: $header . $body,
            kind: EmittedFileKind::HandlerTest,
        );
    }

    private function renderArgs(QueueDispatchSpec $queue): string
    {
        if ($queue->fields === []) {
            return '';
        }

        $args = [];
        foreach ($queue->fields as $name => $type) {
            $args[] = \sprintf('%s: %s', $name, $this->placeholderValue($type));
        }

        return "\n            " . implode(",\n            ", $args) . ",\n        ";
    }

    private function placeholderValue(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => '0',
            'float'           => '0.0',
            'bool', 'boolean' => 'false',
            'string'          => "''",
            default           => \sprintf('new \%s()', $type),
        };
    }

    private function shortNameOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
