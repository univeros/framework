<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Discovery;

use Altair\Messaging\Attribute\AsHandler;
use Altair\Messaging\Exception\InvalidArgumentException;
use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Walks one or more directories and yields the (handler class, attribute)
 * pairs for every class decorated with #[AsHandler].
 *
 * Mirrors the tokenizer-first approach used by
 * Altair\Cli\Discovery\AttributeCommandDiscoverer so we never autoload
 * non-handler classes during scanning.
 */
class AttributeHandlerDiscoverer
{
    /**
     * @param  list<string>                                            $paths
     * @return iterable<array{class: class-string, attribute: AsHandler}>
     */
    public function scan(array $paths): iterable
    {
        $seen = [];
        foreach ($paths as $path) {
            foreach ($this->scanPath($path) as $entry) {
                $key = $entry['class'] . '#' . $entry['attribute']->messageClass;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                yield $entry;
            }
        }
    }

    /**
     * Build a populated HandlerRegistry by scanning the given paths.
     *
     * @param list<string> $paths
     */
    public function buildRegistry(array $paths, ?HandlerRegistry $registry = null): HandlerRegistry
    {
        $registry ??= new HandlerRegistry();
        foreach ($this->scan($paths) as $entry) {
            $registry->registerFromAttribute($entry['class'], $entry['attribute']);
        }

        return $registry;
    }

    /**
     * @return iterable<array{class: class-string, attribute: AsHandler}>
     */
    private function scanPath(string $path): iterable
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(
                \sprintf("Handler scan path '%s' is not a readable directory.", $path),
            );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach ($this->extractClasses((string) $file) as $class) {
                foreach ($this->readHandlerAttributes($class) as $attribute) {
                    yield ['class' => $class, 'attribute' => $attribute];
                }
            }
        }
    }

    /**
     * @return list<class-string>
     */
    private function extractClasses(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false || $code === '') {
            return [];
        }

        $tokens = PhpToken::tokenize($code);

        $namespace = '';
        $classes = [];
        $tokenCount = \count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->readNamespace($tokens, $i);
                continue;
            }

            if (!$token->is(T_CLASS)) {
                continue;
            }

            if ($this->isClassKeywordUsage($tokens, $i)) {
                continue;
            }

            $name = $this->readClassName($tokens, $i);
            if ($name === null) {
                continue;
            }

            $fqcn = $namespace === '' ? $name : $namespace . '\\' . $name;
            /** @var class-string $fqcn */
            $classes[] = $fqcn;
        }

        return $classes;
    }

    /**
     * @return list<AsHandler>
     */
    private function readHandlerAttributes(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return [];
        }

        $attributes = [];
        foreach ($reflection->getAttributes(AsHandler::class) as $attribute) {
            $attributes[] = $attribute->newInstance();
        }

        return $attributes;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function readNamespace(array $tokens, int $start): string
    {
        $tokenCount = \count($tokens);
        $namespace = '';

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
    private function readClassName(array $tokens, int $start): ?string
    {
        $tokenCount = \count($tokens);

        for ($i = $start + 1; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if ($token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            if ($token->is(T_STRING)) {
                return $token->text;
            }

            return null;
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
