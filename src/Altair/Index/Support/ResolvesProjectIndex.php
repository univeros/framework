<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Support;

/**
 * Shared wiring for the index CLI commands: a container may bind a
 * {@see ProjectIndex} (with an explicit root) and it is injected; otherwise one
 * is built from the current working directory. Query commands keep the index
 * fresh with an incremental rebuild unless `--no-build` is passed.
 */
trait ResolvesProjectIndex
{
    public function __construct(private readonly ?ProjectIndex $index = null) {}

    private function index(): ProjectIndex
    {
        return $this->index ?? ProjectIndex::fromCwd();
    }

    /**
     * @return ProjectIndex|null Null means `--no-build` was passed but no index
     *                           exists yet, so the caller should bail with a hint.
     */
    private function readyIndex(bool $noBuild): ?ProjectIndex
    {
        $index = $this->index();

        if ($noBuild) {
            return $index->exists() ? $index : null;
        }

        $index->ensureFresh();

        return $index;
    }
}
