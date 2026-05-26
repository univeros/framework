<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Renderer;

use Altair\AgentSpec\Contracts\ManifestRendererInterface;
use Altair\AgentSpec\Model\AttributeConvention;
use Altair\AgentSpec\Model\ClassEntry;
use Altair\AgentSpec\Model\ContractEntry;
use Altair\AgentSpec\Model\PackageManifest;
use Altair\AgentSpec\Model\TestReference;
use Override;

/**
 * Renders a PackageManifest to deterministic Markdown.
 */
class MarkdownPackageRenderer implements ManifestRendererInterface
{
    #[Override]
    public function render(PackageManifest $manifest): string
    {
        $sections = [
            $this->renderHeader($manifest),
            $this->renderContracts($manifest->contracts),
            $this->renderConcreteClasses($manifest->concreteClasses),
            $this->renderAttributeConventions($manifest->attributeConventions),
            $this->renderCommonPatterns($manifest->commonPatterns),
            $this->renderTests($manifest->testReferences),
            $this->renderRelated($manifest->relatedPackages),
            $this->renderStability($manifest->stabilityNote),
        ];

        $sections = array_values(array_filter($sections, static fn(string $s): bool => $s !== ''));

        return implode("\n\n", $sections) . "\n";
    }

    private function renderHeader(PackageManifest $manifest): string
    {
        $heading = \sprintf('# %s  ·  %s', $manifest->packageName, $manifest->rootNamespace);
        $purpose = $manifest->purpose === ''
            ? '**Purpose:** _(no description provided in composer.json)_'
            : '**Purpose:** ' . $manifest->purpose;

        return $heading . "\n\n" . $purpose;
    }

    /**
     * @param list<ContractEntry> $contracts
     */
    private function renderContracts(array $contracts): string
    {
        if ($contracts === []) {
            return '';
        }

        $lines = ['## Public contracts', '', '| Interface | Method | Returns | Notes |', '|---|---|---|---|'];

        foreach ($contracts as $contract) {
            $notes = $this->formatContractNotes($contract);
            if ($contract->methods === []) {
                $lines[] = \sprintf('| `%s` | _(marker)_ |  | %s |', $contract->shortName, $notes);
                continue;
            }

            $isFirstRow = true;
            foreach ($contract->methods as $method) {
                $lines[] = \sprintf(
                    '| %s | `%s(%s)` | `%s` | %s |',
                    $isFirstRow ? \sprintf('`%s`', $contract->shortName) : '',
                    $this->escapeTable($method->name),
                    $this->escapeTable($method->renderParameters()),
                    $this->escapeTable($method->returnType),
                    $isFirstRow ? $notes : '',
                );
                $isFirstRow = false;
            }
        }

        return implode("\n", $lines);
    }

    private function formatContractNotes(ContractEntry $contract): string
    {
        $notes = [];
        if ($contract->extends !== []) {
            $notes[] = 'extends `' . implode('`, `', $contract->extends) . '`';
        }

        if ($contract->constants !== []) {
            $notes[] = 'constants: `' . implode('`, `', $contract->constants) . '`';
        }

        return implode('; ', $notes);
    }

    /**
     * @param list<ClassEntry> $classes
     */
    private function renderConcreteClasses(array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        $lines = ['## Concrete classes', ''];
        foreach ($classes as $class) {
            $tags = [];
            if ($class->isAbstract) {
                $tags[] = 'abstract';
            }

            if ($class->isFinal) {
                $tags[] = 'final';
            }

            $suffix = $tags === [] ? '' : ' _(' . implode(', ', $tags) . ')_';
            $implements = $class->implements === []
                ? ''
                : ' — implements `' . implode('`, `', $class->implements) . '`';

            $lines[] = \sprintf('- `%s`%s%s', $class->shortName, $suffix, $implements);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<AttributeConvention> $conventions
     */
    private function renderAttributeConventions(array $conventions): string
    {
        if ($conventions === []) {
            return '';
        }

        $lines = [
            '## Request attribute conventions',
            '',
            '| Constant | Value | Declared on |',
            '|---|---|---|',
        ];

        foreach ($conventions as $convention) {
            $lines[] = \sprintf(
                '| `%s` | `%s` | `%s` |',
                $this->escapeTable($convention->constantName),
                $this->escapeTable($convention->value),
                $this->escapeTable($convention->declaringClassShortName),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $patterns
     */
    private function renderCommonPatterns(array $patterns): string
    {
        if ($patterns === []) {
            return '';
        }

        $body = ['## Common patterns', ''];
        foreach ($patterns as $i => $pattern) {
            if ($i > 0) {
                $body[] = '';
            }

            $body[] = $pattern;
        }

        return implode("\n", $body);
    }

    /**
     * @param list<TestReference> $tests
     */
    private function renderTests(array $tests): string
    {
        if ($tests === []) {
            return '';
        }

        $lines = ['## Tests as documentation', ''];
        foreach ($tests as $test) {
            $lines[] = \sprintf('- `%s`', $test->relativePath);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $related
     */
    private function renderRelated(array $related): string
    {
        if ($related === []) {
            return '';
        }

        $lines = ['## Related packages', ''];
        foreach ($related as $name) {
            $lines[] = \sprintf('- `%s`', $name);
        }

        return implode("\n", $lines);
    }

    private function renderStability(string $note): string
    {
        if ($note === '') {
            return '';
        }

        return "## Stability\n\n" . $note;
    }

    private function escapeTable(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}
