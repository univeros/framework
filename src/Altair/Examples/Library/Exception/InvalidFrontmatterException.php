<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library\Exception;

final class InvalidFrontmatterException extends ExamplesException
{
    public static function missingDelimiters(string $path): self
    {
        return new self(\sprintf(
            'Example "%s" is missing the YAML frontmatter delimiters (lines starting with `---`).',
            $path,
        ));
    }

    public static function malformedYaml(string $path, string $reason): self
    {
        return new self(\sprintf(
            'Example "%s" has malformed frontmatter YAML: %s',
            $path,
            $reason,
        ));
    }

    public static function missingField(string $path, string $field): self
    {
        return new self(\sprintf(
            'Example "%s" is missing required frontmatter field "%s".',
            $path,
            $field,
        ));
    }

    public static function wrongFieldType(string $path, string $field, string $expected): self
    {
        return new self(\sprintf(
            'Example "%s" frontmatter field "%s" must be %s.',
            $path,
            $field,
            $expected,
        ));
    }
}
