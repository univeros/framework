<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Database;

use Altair\Mcp\Exception\GuardrailException;

/**
 * Defence-in-depth for the db_query tool: the statement must be a single
 * read-only SELECT/WITH query with no write or DDL keyword and no statement
 * chaining. A determined caller can still write a slow SELECT, but cannot
 * mutate data through this tool.
 */
final class SqlReadGuard
{
    private const string WRITE_KEYWORDS =
        '/\b(insert|update|delete|drop|alter|truncate|create|replace|merge|grant|revoke|attach|pragma|call|set|outfile|dumpfile|load_file|copy|into)\b/i';

    public function assertReadOnly(string $sql): void
    {
        $statement = rtrim(trim($sql), ';');

        if ($statement === '') {
            throw new GuardrailException('Empty SQL statement.');
        }

        if (str_contains($statement, ';')) {
            throw new GuardrailException('Only a single statement is allowed (no ";" chaining).');
        }

        if (preg_match('/^(select|with)\b/i', $statement) !== 1) {
            throw new GuardrailException('Only read-only SELECT (or WITH ... SELECT) queries are allowed.');
        }

        if (preg_match(self::WRITE_KEYWORDS, $statement) === 1) {
            throw new GuardrailException('The query contains a write or DDL keyword; only SELECT is allowed.');
        }
    }
}
