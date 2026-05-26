<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\InputFieldSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Emits a readonly DTO class holding the request inputs.
 *
 * Each spec field becomes a readonly property with the appropriate native
 * type. Validation rules are exposed via a static `rules()` method so
 * `Altair\Validation` can pick them up without reflection.
 */
class InputEmitter
{
    public function __construct(
        private readonly Naming $naming = new Naming(),
        private readonly TypeMapper $typeMapper = new TypeMapper(),
    ) {}

    public function emit(Spec $spec): EmittedFile
    {
        $shortName = $this->naming->inputShortName($spec);
        $namespace = $this->namespaceOf($this->naming->inputFqcn($spec));

        $header = PhpHeader::render($namespace);
        $constructor = $this->renderConstructor($spec);
        $rules = $this->renderRulesMethod($spec);

        $body = <<<PHP
            /**
             * Generated input DTO for {$spec->endpoint->method} {$spec->endpoint->path}.
             */
            final readonly class {$shortName}
            {
            {$constructor}

            {$rules}
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->inputPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::Input,
        );
    }

    private function renderConstructor(Spec $spec): string
    {
        if ($spec->inputs === []) {
            return "    public function __construct() {}\n";
        }

        $lines = ['    public function __construct('];
        $last = \count($spec->inputs) - 1;
        foreach ($spec->inputs as $i => $field) {
            $type = $this->typeMapper->toPhpType($field);
            $nullable = !$field->isRequired() ? '?' : '';
            $default = $this->renderDefault($field);
            $sep = $i === $last ? '' : ',';
            $lines[] = \sprintf('        public %s%s $%s%s%s', $nullable, $type, $field->name, $default, $sep);
        }
        $lines[] = '    ) {}';

        return implode("\n", $lines);
    }

    private function renderDefault(InputFieldSpec $field): string
    {
        if ($field->hasDefault) {
            if ($field->isEnum() && \is_string($field->default)) {
                return \sprintf(' = \\%s::%s', ltrim($field->of ?? '', '\\'), $field->default);
            }
            return ' = ' . var_export($field->default, true);
        }

        if (!$field->isRequired()) {
            return ' = null';
        }

        return '';
    }

    private function renderRulesMethod(Spec $spec): string
    {
        $entries = [];
        foreach ($spec->inputs as $field) {
            $quoted = array_map(static fn(string $r): string => \sprintf("'%s'", addslashes($r)), $field->rules);
            $entries[] = \sprintf("            '%s' => [%s],", $field->name, implode(', ', $quoted));
        }

        $rulesBody = $entries === []
            ? "        return [];"
            : "        return [\n" . implode("\n", $entries) . "\n        ];";

        return <<<PHP
                /**
                 * Validation rules per field, consumed by Altair\\Validation.
                 *
                 * @return array<string, list<string>>
                 */
                public static function rules(): array
                {
            {$rulesBody}
                }
            PHP;
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }
}
