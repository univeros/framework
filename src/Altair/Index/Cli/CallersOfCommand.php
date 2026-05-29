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
use Altair\Index\Model\Usage;
use Altair\Index\Support\Json;
use Altair\Index\Support\ProjectIndex;
use Altair\Index\Support\ResolvesProjectIndex;
use Altair\Index\Support\View;

/**
 * `bin/altair index:callers-of "App\\User\\CreateUser::__invoke"` — call sites
 * of a method, each with the calling scope as context.
 *
 * Resolution is AST-only: static, `self::`, `parent::`, and `$this->` calls are
 * linked; a call on an untyped variable cannot be (that needs type inference).
 */
#[Command(
    name: 'index:callers-of',
    description: 'List the call sites of a method (static/self/parent/$this calls).',
)]
final readonly class CallersOfCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Argument(description: 'Fully-qualified method, e.g. "App\\User\\CreateUser::__invoke".')]
        string $method,
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

        $callers = $index->usages()->callers($method);

        echo $format === 'json'
            ? Json::encode([
                'method' => $method,
                'count' => \count($callers),
                'callers' => array_map(static fn(Usage $u): array => $u->toArray(), $callers),
            ])
            : View::usageLines($callers, \sprintf('No callers of %s.', $method));

        return 0;
    }
}
