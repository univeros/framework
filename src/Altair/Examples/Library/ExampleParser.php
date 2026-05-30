<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library;

use Altair\Examples\Library\Exception\InvalidFrontmatterException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses an example file (YAML frontmatter + Markdown body) into an {@see Example}.
 *
 * Every example file must begin with a YAML frontmatter block delimited by
 * lines containing exactly `---`. Everything after the closing delimiter is
 * the Markdown body. Required frontmatter fields: `title`, `scenario`,
 * `packages` (list of strings), `since`, `tested_by`. Unknown fields are
 * preserved on parse but discarded on the round trip — the schema is closed
 * so authors don't accidentally rely on undocumented metadata.
 */
final readonly class ExampleParser
{
    private const array REQUIRED_STRING_FIELDS = ['title', 'scenario', 'since', 'tested_by'];

    /**
     * @param string $id     Stable identifier (e.g. `http/basic-endpoint`)
     * @param string $source Raw file contents
     */
    public function parse(string $id, string $source): Example
    {
        [$frontmatterRaw, $body] = $this->split($id, $source);
        $frontmatter = $this->decodeFrontmatter($id, $frontmatterRaw);

        foreach (self::REQUIRED_STRING_FIELDS as $field) {
            if (!\array_key_exists($field, $frontmatter)) {
                throw InvalidFrontmatterException::missingField($id, $field);
            }

            if (!\is_string($frontmatter[$field]) || trim($frontmatter[$field]) === '') {
                throw InvalidFrontmatterException::wrongFieldType($id, $field, 'a non-empty string');
            }
        }

        if (!\array_key_exists('packages', $frontmatter)) {
            throw InvalidFrontmatterException::missingField($id, 'packages');
        }

        $packages = $frontmatter['packages'];
        if (!\is_array($packages) || array_keys($packages) !== range(0, \count($packages) - 1)) {
            throw InvalidFrontmatterException::wrongFieldType($id, 'packages', 'a list of strings');
        }

        $normalisedPackages = [];
        foreach ($packages as $pkg) {
            if (!\is_string($pkg) || trim($pkg) === '') {
                throw InvalidFrontmatterException::wrongFieldType($id, 'packages', 'a list of strings');
            }

            $normalisedPackages[] = $pkg;
        }

        return new Example(
            id: $id,
            title: $frontmatter['title'],
            scenario: $frontmatter['scenario'],
            packages: $normalisedPackages,
            since: $frontmatter['since'],
            testedBy: $frontmatter['tested_by'],
            body: $body,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $id, string $source): array
    {
        $normalised = preg_replace('/\R/u', "\n", $source) ?? $source;
        $trimmed = ltrim($normalised, "\u{FEFF} \n");

        if (!str_starts_with($trimmed, "---\n")) {
            throw InvalidFrontmatterException::missingDelimiters($id);
        }

        $afterOpen = substr($trimmed, 4);
        $closePos = strpos($afterOpen, "\n---");
        if ($closePos === false) {
            throw InvalidFrontmatterException::missingDelimiters($id);
        }

        $frontmatter = substr($afterOpen, 0, $closePos);
        $rest = substr($afterOpen, $closePos + 4);
        $body = ltrim($rest, "\n");

        return [$frontmatter, $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFrontmatter(string $id, string $yaml): array
    {
        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException $parseException) {
            throw InvalidFrontmatterException::malformedYaml($id, $parseException->getMessage());
        }

        if (!\is_array($parsed)) {
            throw InvalidFrontmatterException::malformedYaml(
                $id,
                'top-level value must be a mapping, got ' . get_debug_type($parsed),
            );
        }

        return $parsed;
    }
}
