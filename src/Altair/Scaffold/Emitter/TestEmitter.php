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
 * Emits a PHPUnit test covering action wiring + each spec-declared response
 * status (golden path + every error status the spec promises).
 */
class TestEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        $shortName = $this->naming->testShortName($spec);
        $actionFqcn = $this->naming->actionFqcn($spec);
        $inputFqcn = $this->naming->inputFqcn($spec);
        $responderFqcn = $this->naming->responderFqcn($spec);
        $testNamespace = 'Tests\\Http\\Actions';

        $statusAssertions = $this->renderStatusAssertions($spec);

        $header = PhpHeader::render($testNamespace);
        $body = <<<PHP
            use {$actionFqcn};
            use {$inputFqcn};
            use {$responderFqcn};
            use PHPUnit\\Framework\\TestCase;

            final class {$shortName} extends TestCase
            {
                public function testActionWiresInputResponderAndDomain(): void
                {
                    \$action = new \\{$actionFqcn}();

                    self::assertSame(\\{$inputFqcn}::class, \$action->getInputClassName());
                    self::assertSame(\\{$responderFqcn}::class, \$action->getResponderClassName());
                }

                public function testSpecDeclaredStatusesAreProducibleByResponder(): void
                {
                    \$declared = \\{$responderFqcn}::statuses();

            {$statusAssertions}
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->testPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::Test,
        );
    }

    private function renderStatusAssertions(Spec $spec): string
    {
        if ($spec->outputs === []) {
            return '        self::assertSame([], $declared);';
        }

        $lines = [];
        foreach ($spec->outputs as $output) {
            $lines[] = \sprintf('        self::assertContains(%d, $declared);', $output->status);
        }

        return implode("\n", $lines);
    }
}
