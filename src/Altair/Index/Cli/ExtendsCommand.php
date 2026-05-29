<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Index\Support\Json;
use Altair\Index\Support\ProjectIndex;
use Altair\Index\Support\ResolvesProjectIndex;
use Altair\Index\Support\View;

/**
 * `bin/altair index:extends "App\\Base\\Entity"` — the classes (or interfaces)
 * that declare `extends <class>`.
 */
#[Command(
    name: 'index:extends',
    description: 'List the classes (or interfaces) that extend a given class.',
)]
final readonly class ExtendsCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Argument(description: 'Fully-qualified class (or interface) name.')]
        string $class,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Query the existing index without rebuilding first.', name: 'no-build')]
        bool $noBuild = false,
    ): int {
        $index = $this->readyIndex($noBuild);
        if (!$index instanceof ProjectIndex) {
            echo "No index found. Run `bin/altair index:build` first.\n";

            return 2;
        }

        $extenders = $index->usages()->extenders($class);

        echo $format === 'json'
            ? Json::encode(['class' => $class, 'count' => \count($extenders), 'extenders' => $extenders])
            : View::nameLines($extenders, \sprintf('No classes extend %s.', $class));

        return 0;
    }
}
