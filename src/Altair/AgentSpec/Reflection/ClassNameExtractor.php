<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Reflection;

use PhpToken;

/**
 * Token-based class/interface/trait/enum name extractor.
 * Reads PHP source without including it so we can decide whether to reflect.
 */
class ClassNameExtractor
{
    /**
     * @return list<class-string>
     */
    public function extract(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false || $code === '') {
            return [];
        }

        $tokens = array_values(PhpToken::tokenize($code));
        $namespace = '';
        $classes = [];
        $tokenCount = \count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->readNamespace($tokens, $i);
                continue;
            }

            if (!$token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                continue;
            }

            if ($token->is(T_CLASS) && $this->isClassKeywordUsage($tokens, $i)) {
                continue;
            }

            $shortName = $this->readDeclarationName($tokens, $i);
            if ($shortName === null) {
                continue;
            }

            $fqcn = $namespace === '' ? $shortName : $namespace . '\\' . $shortName;
            /** @var class-string $fqcn */
            $classes[] = $fqcn;
        }

        return $classes;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function readNamespace(array $tokens, int $start): string
    {
        $namespace = '';
        $tokenCount = \count($tokens);

        for ($i = $start + 1; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if ($token->text === ';' || $token->text === '{') {
                break;
            }

            if ($token->is([T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $namespace .= $token->text;
            }
        }

        return trim($namespace, '\\');
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function readDeclarationName(array $tokens, int $start): ?string
    {
        $tokenCount = \count($tokens);

        for ($i = $start + 1; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if ($token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            return $token->is(T_STRING) ? $token->text : null;
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function isClassKeywordUsage(array $tokens, int $position): bool
    {
        for ($i = $position - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if ($token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            return $token->is([T_DOUBLE_COLON, T_NEW]);
        }

        return false;
    }
}
