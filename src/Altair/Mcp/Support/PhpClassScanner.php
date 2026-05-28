<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Support;

use const DIRECTORY_SEPARATOR;
use const T_CLASS;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_WHITESPACE;

use function token_get_all;

/**
 * Extracts fully-qualified class names from PHP source by token scan — without
 * including the file. Used to discover #[McpTool] tools and #[Command] classes.
 */
final class PhpClassScanner
{
    /**
     * Non-recursive: the FQCN of the first class declared in each `*.php` file
     * directly under the directory.
     *
     * @return list<class-string>
     */
    public function classesIn(string $directory): array
    {
        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $classes = [];
        foreach ($files as $file) {
            $fqcn = $this->fqcnInFile($file);
            if ($fqcn !== null) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    /**
     * @return class-string|null
     */
    public function fqcnInFile(string $file): ?string
    {
        $source = @file_get_contents($file);
        if ($source === false) {
            return null;
        }

        $tokens = \token_get_all($source);
        $namespace = '';
        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $i + 1);
            }

            if ($token[0] === T_CLASS) {
                // Skip anonymous classes (`new class {}`) — they have no name.
                if ($this->precededByNew($tokens, $i)) {
                    continue;
                }

                $class = $this->readClassName($tokens, $i + 1);
                if ($class === null) {
                    return null;
                }

                $fqcn = $namespace === '' ? $class : $namespace . '\\' . $class;

                /** @var class-string $fqcn */
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * Was the token at $index preceded (ignoring whitespace) by `new`?
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function precededByNew(array $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (\is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return \is_array($token) && $token[0] === T_NEW;
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readName(array $tokens, int $start): string
    {
        $name = '';
        $count = \count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (\is_array($token) && \in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
                continue;
            }

            if ($token === ';' || $token === '{') {
                break;
            }
        }

        return trim($name, '\\');
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readClassName(array $tokens, int $start): ?string
    {
        $count = \count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (\is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
    }
}
