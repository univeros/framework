<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Discovery;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Contracts\CommandLocatorInterface;
use Altair\Cli\Exception\InvalidArgumentException;
use FilesystemIterator;
use Override;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Discovers classes decorated with #[Command] by walking the given
 * directories, parsing the namespace/class declaration of each PHP file,
 * and reflecting on the discovered class names.
 */
class AttributeCommandDiscoverer implements CommandLocatorInterface
{
    /**
     * @param  list<string>             $paths
     * @return iterable<class-string>
     */
    #[Override]
    public function scan(array $paths): iterable
    {
        $found = [];
        foreach ($paths as $path) {
            foreach ($this->scanPath($path) as $class) {
                if (isset($found[$class])) {
                    continue;
                }

                $found[$class] = true;

                yield $class;
            }
        }
    }

    /**
     * @return iterable<class-string>
     */
    private function scanPath(string $path): iterable
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(
                \sprintf("Path '%s' is not a readable directory.", $path),
            );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach ($this->extractClasses((string) $file) as $class) {
                if (!$this->hasCommandAttribute($class)) {
                    continue;
                }

                yield $class;
            }
        }
    }

    /**
     * Extract fully-qualified class names declared in a PHP file. Uses the
     * native tokenizer so we never have to execute or include the file
     * before deciding whether to reflect on it.
     *
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

            // Skip `::class` and `new class` (anonymous) usages.
            if ($this->isClassKeywordUsage($tokens, $i)) {
                continue;
            }

            $className = $this->readClassName($tokens, $i);
            if ($className === null) {
                continue;
            }

            $fqcn = $namespace === '' ? $className : $namespace . '\\' . $className;
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
     * Returns true when the `class` keyword is being used as `Foo::class`
     * or `new class {}` rather than as a class declaration.
     *
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

    private function hasCommandAttribute(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return false;
        }

        return $reflection->getAttributes(Command::class) !== [];
    }
}
