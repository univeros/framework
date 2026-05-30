<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library;

/**
 * A single parsed example: its identifier, frontmatter metadata, and Markdown body.
 *
 * The identifier is the example's path relative to the library root with the
 * `.md` extension stripped (e.g. `http/basic-endpoint`). It doubles as the
 * stable key the CLI, MCP tools, and the index all key on.
 */
final readonly class Example
{
    /**
     * @param list<string> $packages
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $scenario,
        public array $packages,
        public string $since,
        public string $testedBy,
        public string $body,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     scenario: string,
     *     packages: list<string>,
     *     since: string,
     *     tested_by: string
     * }
     */
    public function toIndexEntry(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scenario' => $this->scenario,
            'packages' => $this->packages,
            'since' => $this->since,
            'tested_by' => $this->testedBy,
        ];
    }
}
