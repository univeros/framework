<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Resolver;

use Altair\TestReporter\Result\SourceLocation;

use const DIRECTORY_SEPARATOR;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * Three-signal heuristic for mapping a failing test back to the
 * production source it most likely targets — in order:
 *
 *   1. `#[CoversClass(X::class)]` / `#[CoversFunction('x')]` attributes
 *      on the test class or method (authoritative)
 *   2. `@covers` annotation in the doc comment (legacy fallback)
 *   3. Namespace heuristic: `Altair\Tests\Http\Support\HttpCacheTest`
 *      → `Altair\Http\Support\HttpCache`; walk methods for a name that
 *      matches the test method's suffix (`testFooBar` → `fooBar`).
 *
 * When no signal yields a match, returns an empty list so the writer
 * emits `source_under_test: []` and the agent knows it's on its own.
 */
final readonly class SourceUnderTestResolver
{
    public function __construct(
        private string $projectRoot,
    ) {}

    /**
     * @return list<SourceLocation>
     */
    public function resolve(string $testClass, string $testMethod): array
    {
        if (!class_exists($testClass)) {
            return [];
        }

        $reflection = new ReflectionClass($testClass);

        $sources = $this->fromAttributes($reflection, $testMethod);
        if ($sources !== []) {
            return $sources;
        }

        $sources = $this->fromCoversAnnotation($reflection, $testMethod);
        if ($sources !== []) {
            return $sources;
        }

        return $this->fromNamespaceHeuristic($reflection, $testMethod);
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<SourceLocation>
     */
    private function fromAttributes(ReflectionClass $reflection, string $testMethod): array
    {
        $out = [];

        foreach ($reflection->getAttributes(CoversClass::class) as $attr) {
            /** @var CoversClass $instance */
            $instance = $attr->newInstance();
            $location = $this->locateClass($instance->className(), $this->stripTestPrefix($testMethod));
            if ($location instanceof SourceLocation) {
                $out[] = $location;
            }
        }

        foreach ($reflection->getAttributes(CoversFunction::class) as $attr) {
            /** @var CoversFunction $instance */
            $instance = $attr->newInstance();
            $location = $this->locateFunction($instance->functionName());
            if ($location instanceof SourceLocation) {
                $out[] = $location;
            }
        }

        if ($reflection->hasMethod($testMethod)) {
            $method = $reflection->getMethod($testMethod);
            foreach ($method->getAttributes(CoversClass::class) as $attr) {
                /** @var CoversClass $instance */
                $instance = $attr->newInstance();
                $location = $this->locateClass($instance->className(), $this->stripTestPrefix($testMethod));
                if ($location instanceof SourceLocation) {
                    $out[] = $location;
                }
            }
        }

        return $out;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<SourceLocation>
     */
    private function fromCoversAnnotation(ReflectionClass $reflection, string $testMethod): array
    {
        $out = [];
        $docs = [$reflection->getDocComment()];
        if ($reflection->hasMethod($testMethod)) {
            $docs[] = $reflection->getMethod($testMethod)->getDocComment();
        }

        foreach ($docs as $doc) {
            if (!\is_string($doc)) {
                continue;
            }

            if ($doc === '') {
                continue;
            }

            if (preg_match_all('/@covers\s+(\\\?[A-Za-z_][\\\A-Za-z0-9_]*)(::([A-Za-z_]\w*))?/', $doc, $matches, PREG_SET_ORDER) !== false) {
                foreach ($matches as $match) {
                    $name = ltrim($match[1], '\\');
                    $methodName = $match[3] ?? null;
                    if (class_exists($name) || interface_exists($name)) {
                        $location = $this->locateClass($name, $methodName ?? $testMethod);
                        if ($location instanceof SourceLocation) {
                            $out[] = $location;
                        }
                    } elseif (\function_exists($name)) {
                        $location = $this->locateFunction($name);
                        if ($location instanceof SourceLocation) {
                            $out[] = $location;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<SourceLocation>
     */
    private function fromNamespaceHeuristic(ReflectionClass $reflection, string $testMethod): array
    {
        $testClass = $reflection->getName();
        if (!str_ends_with($testClass, 'Test')) {
            return [];
        }

        $methodGuess = $this->stripTestPrefix($testMethod);

        // Two candidate forms — try in this order:
        //   1. With a `\Tests\` segment stripped (production layout:
        //      Altair\Tests\Http\Support\HttpCacheTest → Altair\Http\Support\HttpCache).
        //   2. As-is, just dropping the trailing `Test` suffix (handles
        //      fixture classes that live inside the test tree itself).
        $candidates = [];
        $stripped = preg_replace('/\\\\Tests\\\\/', '\\', $testClass, 1);
        if (\is_string($stripped) && $stripped !== $testClass) {
            $candidates[] = substr($stripped, 0, -4);
        }

        $candidates[] = substr($testClass, 0, -4);

        foreach ($candidates as $candidate) {
            if (!class_exists($candidate) && !interface_exists($candidate)) {
                continue;
            }

            $location = $this->locateClass($candidate, $methodGuess);
            if ($location instanceof SourceLocation) {
                return [$location];
            }
        }

        return [];
    }

    private function locateClass(string $className, string $preferredMethod): ?SourceLocation
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (Throwable) {
            return null;
        }

        $file = $reflection->getFileName();
        if ($file === false) {
            return null;
        }

        $method = $this->findMethod($reflection, $preferredMethod);
        if ($method instanceof ReflectionMethod) {
            return new SourceLocation(
                file: $this->relativise($file),
                method: $method->getName(),
                lines: $this->lineRange($method),
            );
        }

        return new SourceLocation(file: $this->relativise($file));
    }

    private function locateFunction(string $functionName): ?SourceLocation
    {
        if (!\function_exists($functionName)) {
            return null;
        }

        try {
            $reflection = new ReflectionFunction($functionName);
        } catch (Throwable) {
            return null;
        }

        $file = $reflection->getFileName();
        if ($file === false) {
            return null;
        }

        return new SourceLocation(
            file: $this->relativise($file),
            method: $functionName,
            lines: \sprintf('%d-%d', $reflection->getStartLine(), $reflection->getEndLine()),
        );
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function findMethod(ReflectionClass $reflection, string $preferred): ?ReflectionMethod
    {
        if ($reflection->hasMethod($preferred)) {
            return $reflection->getMethod($preferred);
        }

        $lower = strtolower($preferred);
        foreach ($reflection->getMethods() as $method) {
            if (strtolower($method->getName()) === $lower) {
                return $method;
            }
        }

        // Prefix match: test method names typically extend the source
        // method name (e.g. `isCacheableReturnsTrueWithMaxAge` covers
        // `isCacheable`). Pick the longest matching prefix so we
        // resolve to the most specific method when several would match.
        $best = null;
        $bestLen = 0;
        foreach ($reflection->getMethods() as $method) {
            $methodLower = strtolower($method->getName());
            if (str_starts_with($lower, $methodLower) && \strlen($methodLower) > $bestLen) {
                $best = $method;
                $bestLen = \strlen($methodLower);
            }
        }

        return $best;
    }

    private function lineRange(ReflectionMethod $method): string
    {
        return \sprintf('%d-%d', $method->getStartLine(), $method->getEndLine());
    }

    private function relativise(string $absolute): string
    {
        $prefix = rtrim($this->projectRoot, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $prefix)) {
            return substr($absolute, \strlen($prefix));
        }

        return $absolute;
    }

    private function stripTestPrefix(string $testMethod): string
    {
        if (str_starts_with($testMethod, 'test')) {
            $rest = substr($testMethod, 4);
            if ($rest !== '') {
                return lcfirst($rest);
            }
        }

        return $testMethod;
    }
}
