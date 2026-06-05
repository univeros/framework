<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_FILENAME;

use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Inlines external (relative-file) `$ref`s into a single self-contained
 * document so {@see OpenApiParser} can resolve them like internal component
 * refs. The classic multi-file pattern — a spec that splits its schemas across
 * sibling files (`$ref: './schemas/pet.yaml#/Pet'`) — bundles instead of
 * failing with "ref not defined in components/schemas".
 *
 * Security (the document and the files it references may be untrusted):
 *
 * - **No remote fetch.** A `http(s)://`, protocol-relative (`//host`), or any
 *   other URI-scheme ref is rejected (warned, left unresolved). Only local
 *   files are read.
 * - **No path escape.** A file ref is resolved relative to the referring file's
 *   directory, canonicalized with `realpath`, and must stay inside the base
 *   directory subtree (the root document's directory). Absolute paths and `..`
 *   that escapes the base are rejected.
 * - **Bounded.** Caps on distinct files ({@see MAX_FILES}), per-file size
 *   ({@see MAX_FILE_BYTES}), and ref-chase depth ({@see MAX_DEPTH}); cyclic and
 *   repeated refs reuse the first synthesized name instead of looping.
 *
 * A ref that cannot be bundled is left untouched, so the downstream
 * {@see \Altair\Scaffold\Spec\Emitter\SchemaMapper} still surfaces it as an
 * unmappable schema — bundling never silences a ref, it only resolves the safe
 * ones.
 */
final class RefBundler
{
    private const int MAX_FILES = 32;

    private const int MAX_DEPTH = 32;

    private const int MAX_FILE_BYTES = 1_000_000;

    /** Structural-walk ceiling — bounds nested-mapping depth so a pathological document cannot exhaust the call stack. */
    private const int MAX_WALK_DEPTH = 64;

    private readonly string $baseDir;

    /** @var array<string, array<string, mixed>> Canonical path => decoded document (load cache). */
    private array $documents = [];

    /** @var array<string, string> "canonicalPath\0pointer" => synthesized component name. */
    private array $names = [];

    /** @var array<string, array<string, mixed>> Synthesised name => bundled schema. */
    private array $schemas = [];

    /** @var array<string, true> Component names already in use (root + synthesized). */
    private array $used = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(string $baseDir)
    {
        $real = realpath($baseDir);
        $this->baseDir = $real !== false ? $real : rtrim($baseDir, DIRECTORY_SEPARATOR);
    }

    /**
     * @param array<string, mixed> $document
     */
    public function bundle(array $document): BundleResult
    {
        $this->documents = [];
        $this->names = [];
        $this->schemas = [];
        $this->used = $this->existingComponentNames($document);
        $this->warnings = [];

        /** @var array<string, mixed> $rewritten */
        $rewritten = $this->rewrite($document, null, 0);

        if ($this->schemas !== []) {
            $rewritten = $this->mergeSynthesised($rewritten);
        }

        return new BundleResult($rewritten, $this->warnings);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, true>
     */
    private function existingComponentNames(array $document): array
    {
        $components = $document['components'] ?? null;
        $schemas = \is_array($components) && \is_array($components['schemas'] ?? null) ? $components['schemas'] : [];

        $used = [];
        foreach (array_keys($schemas) as $name) {
            if (\is_string($name)) {
                $used[$name] = true;
            }
        }

        return $used;
    }

    /**
     * @param  array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function mergeSynthesised(array $document): array
    {
        $components = \is_array($document['components'] ?? null) ? $document['components'] : [];
        $schemas = \is_array($components['schemas'] ?? null) ? $components['schemas'] : [];

        foreach ($this->schemas as $name => $schema) {
            $schemas[$name] = $schema;
        }

        $components['schemas'] = $schemas;
        $document['components'] = $components;

        return $document;
    }

    /**
     * Returns a copy of $node with every external `$ref` rewritten to an
     * internal one. $contextFile is the canonical path of the file the node
     * came from, or null for the root document (whose internal refs stay
     * internal). $depth bounds external-ref chasing, not the structural walk.
     *
     * @phpstan-impure accumulates inlined schemas/warnings into instance state.
     */
    private function rewrite(mixed $node, ?string $contextFile, int $depth, int $walkDepth = 0): mixed
    {
        if (!\is_array($node)) {
            return $node;
        }

        if ($walkDepth >= self::MAX_WALK_DEPTH) {
            $this->warn('document nesting exceeds the maximum walk depth; left unprocessed.');

            return $node;
        }

        $ref = $node['$ref'] ?? null;
        if (\is_string($ref)) {
            $internal = $this->resolveRef($ref, $contextFile, $depth);

            // Per JSON Reference, sibling keys of a `$ref` are ignored, so a
            // resolved ref collapses to a single internal pointer.
            return $internal !== null ? ['$ref' => $internal] : $node;
        }

        $out = [];
        foreach ($node as $key => $value) {
            $out[$key] = $this->rewrite($value, $contextFile, $depth, $walkDepth + 1);
        }

        return $out;
    }

    /**
     * Resolves one `$ref` to an internal `#/components/schemas/<name>` pointer,
     * inlining the target schema as a side effect. Returns null when the ref is
     * internal-to-root (left as-is) or cannot be safely bundled (warned).
     *
     * @phpstan-impure inlines the resolved schema into instance state.
     */
    private function resolveRef(string $ref, ?string $contextFile, int $depth): ?string
    {
        [$file, $pointer] = $this->splitRef($ref);

        if ($file === '') {
            if ($contextFile === null) {
                return null; // internal ref in the root document — already resolvable.
            }

            $canonical = $contextFile; // internal ref inside an external file → that file.
        } else {
            $dir = $contextFile !== null ? \dirname($contextFile) : $this->baseDir;
            $canonical = $this->safeResolve($file, $dir, $ref);
            if ($canonical === null) {
                return null;
            }
        }

        $key = $canonical . "\0" . $pointer;
        if (isset($this->names[$key])) {
            return '#/components/schemas/' . $this->names[$key]; // dedupe / cycle.
        }

        if ($depth >= self::MAX_DEPTH) {
            $this->warn(\sprintf('external `$ref` chain exceeded depth %d at `%s`; not bundled.', self::MAX_DEPTH, $ref));

            return null;
        }

        $document = $this->load($canonical, $ref);
        if ($document === null) {
            return null;
        }

        $target = $this->pointer($document, $pointer);
        if (!\is_array($target)) {
            $this->warn(\sprintf('external `$ref` `%s` does not resolve to a schema; not bundled.', $ref));

            return null;
        }

        $name = $this->synthesizeName($pointer, $canonical);
        $this->names[$key] = $name;
        $this->used[$name] = true;
        // Register the name before recursing so a cyclic ref reuses it.
        /** @var array<string, mixed> $bundled */
        $bundled = $this->rewrite($target, $canonical, $depth + 1);
        $this->schemas[$name] = $bundled;

        return '#/components/schemas/' . $name;
    }

    /**
     * @return array{0: string, 1: string} [file, pointer] — pointer keeps its leading slash, e.g. `/components/schemas/Pet`.
     */
    private function splitRef(string $ref): array
    {
        $hash = strpos($ref, '#');
        if ($hash === false) {
            return [$ref, ''];
        }

        return [substr($ref, 0, $hash), substr($ref, $hash + 1)];
    }

    /**
     * Validates and canonicalizes a relative file ref, returning the absolute
     * path only when it is a readable local file inside the base subtree.
     */
    private function safeResolve(string $file, string $dir, string $ref): ?string
    {
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $file) === 1 || str_starts_with($file, '//')) {
            $this->warn(\sprintf('remote `$ref` `%s` is not fetched; not bundled.', $ref));

            return null;
        }

        if ($this->isAbsolute($file)) {
            $this->warn(\sprintf('absolute `$ref` path `%s` is not allowed; not bundled.', $ref));

            return null;
        }

        $real = realpath($dir . DIRECTORY_SEPARATOR . $file);
        if ($real === false || !is_file($real)) {
            $this->warn(\sprintf('external `$ref` file `%s` was not found; not bundled.', $ref));

            return null;
        }

        if ($real !== $this->baseDir && !str_starts_with($real, $this->baseDir . DIRECTORY_SEPARATOR)) {
            $this->warn(\sprintf('external `$ref` `%s` escapes the document directory; not bundled.', $ref));

            return null;
        }

        return $real;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function load(string $canonical, string $ref): ?array
    {
        if (isset($this->documents[$canonical])) {
            return $this->documents[$canonical];
        }

        if (\count($this->documents) >= self::MAX_FILES) {
            $this->warn(\sprintf('external `$ref` file budget (%d) exceeded at `%s`; not bundled.', self::MAX_FILES, $ref));

            return null;
        }

        $size = filesize($canonical);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            $this->warn(\sprintf('external `$ref` file `%s` is too large; not bundled.', $ref));

            return null;
        }

        $contents = @file_get_contents($canonical);
        if ($contents === false) {
            $this->warn(\sprintf('external `$ref` file `%s` is not readable; not bundled.', $ref));

            return null;
        }

        try {
            $decoded = Yaml::parse($contents);
        } catch (Throwable) {
            $this->warn(\sprintf('external `$ref` file `%s` is not valid YAML/JSON; not bundled.', $ref));

            return null;
        }

        if (!\is_array($decoded)) {
            $this->warn(\sprintf('external `$ref` file `%s` is not a map; not bundled.', $ref));

            return null;
        }

        $this->documents[$canonical] = $decoded;

        return $decoded;
    }

    /**
     * Resolves a JSON Pointer (RFC 6901) within a decoded document. An empty
     * pointer is the whole document.
     *
     * @param array<string, mixed> $document
     */
    private function pointer(array $document, string $pointer): mixed
    {
        if ($pointer === '') {
            return $document; // a whole-file ref (`file.yaml` with no fragment).
        }

        $node = $document;
        foreach (explode('/', ltrim($pointer, '/')) as $rawSegment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($rawSegment));
            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * A clean, collision-free PascalCase component name derived from the ref's
     * last pointer segment (or the file name when the whole file is referenced).
     */
    private function synthesizeName(string $pointer, string $canonical): string
    {
        $segments = array_values(array_filter(explode('/', $pointer), static fn(string $s): bool => $s !== ''));
        $last = $segments === [] ? pathinfo($canonical, PATHINFO_FILENAME) : end($segments);
        $base = $this->pascalCase(str_replace(['~1', '~0'], ['/', '~'], rawurldecode((string) $last)));
        if ($base === '') {
            $base = 'External';
        }

        $name = $base;
        $suffix = 2;
        while (isset($this->used[$name])) {
            $name = $base . $suffix;
            ++$suffix;
        }

        return $name;
    }

    private function pascalCase(string $value): string
    {
        $words = array_filter(preg_split('/[^a-zA-Z0-9]+/', $value) ?: []);

        return implode('', array_map(ucfirst(...), $words));
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
