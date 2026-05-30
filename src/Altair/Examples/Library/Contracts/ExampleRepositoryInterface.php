<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library\Contracts;

use Altair\Examples\Library\Example;

interface ExampleRepositoryInterface
{
    /**
     * Every example in the library, sorted by id ascending.
     *
     * @return list<Example>
     */
    public function findAll(): array;

    /**
     * Look up a single example by its identifier (the path relative to the
     * library root with the `.md` extension stripped, e.g. `http/basic-endpoint`).
     *
     * @throws \Altair\Examples\Library\Exception\ExampleNotFoundException
     */
    public function findById(string $id): Example;

    /**
     * Every example that lists the given package in its `packages` frontmatter.
     *
     * @return list<Example>
     */
    public function findByPackage(string $package): array;

    /**
     * Free-text substring search across id, title, scenario, and body.
     *
     * @return list<Example>
     */
    public function search(string $query): array;
}
