<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Parser;

use Altair\Index\Model\ParsedFile;
use Altair\Index\Model\Usage;
use Altair\Index\Model\UsageKind;
use Altair\Scaffold\Emitter\Naming;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Parser;
use Throwable;

/**
 * Turns an Altair endpoint spec into framework-aware usages.
 *
 * A spec does not declare framework symbols; it references them. So this walker
 * emits usages only: a `spec_endpoint` usage of the domain handler and of the
 * generated Action class, and (when a `persistence:` block is present) a
 * `spec_entity` usage of the entity and its repository. That is what lets
 * `find-usages App\\User\\User` surface the specs that drive it. The YAML AST
 * carries no line numbers, so spec usages are recorded at line 0.
 */
final readonly class YamlSpecWalker
{
    public function __construct(
        private Parser $parser = new Parser(),
        private Naming $naming = new Naming(),
    ) {}

    public function walk(string $path, string $content): ParsedFile
    {
        $hash = ParsedFile::hash($content);

        try {
            $spec = $this->parser->parseString($content, $path);
        } catch (Throwable) {
            return new ParsedFile($path, $hash, [], []);
        }

        return new ParsedFile($path, $hash, [], $this->usagesFor($spec, $path));
    }

    /**
     * @return list<Usage>
     */
    private function usagesFor(Spec $spec, string $path): array
    {
        $context = trim($spec->endpoint->method . ' ' . $spec->endpoint->path);

        $usages = [
            new Usage($spec->domain->class, $path, 0, UsageKind::SpecEndpoint, $context),
            new Usage($this->naming->actionFqcn($spec), $path, 0, UsageKind::SpecEndpoint, $context),
        ];

        if ($spec->persistence instanceof PersistenceSpec) {
            $usages[] = new Usage($spec->persistence->entity->class, $path, 0, UsageKind::SpecEntity, $context);
            if ($spec->persistence->repository !== '') {
                $usages[] = new Usage($spec->persistence->repository, $path, 0, UsageKind::SpecEntity, $context);
            }
        }

        return array_values(array_filter($usages, static fn(Usage $u): bool => $u->fqn !== ''));
    }
}
