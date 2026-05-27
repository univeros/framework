<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Altair\Persistence\Cycle\CycleRepository;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Templating\PhpHeader;
use Cycle\ORM\ORMInterface;
use LogicException;

/**
 * Emits a domain-specific repository extending {@see \Altair\Persistence\Cycle\CycleRepository}.
 *
 * The generated repository pins the entity class so the rest of the app can
 * use it as `UserRepository` without leaking Cycle-specific types into
 * callers.
 */
final readonly class RepositoryEmitter
{
    public function __construct(private Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        if (!$spec->persistence instanceof PersistenceSpec || $spec->persistence->repository === '') {
            throw new LogicException('RepositoryEmitter requires a persistence block with a repository class.');
        }

        $repositoryFqcn = $spec->persistence->repository;
        $entityFqcn = $spec->persistence->entity->class;

        $namespace = $this->namespaceOf($repositoryFqcn);
        $shortName = $this->shortNameOf($repositoryFqcn);
        $entityShortName = $this->shortNameOf($entityFqcn);
        $entityNamespace = $this->namespaceOf($entityFqcn);

        $imports = [
            UnitOfWorkInterface::class,
            CycleRepository::class,
            ORMInterface::class,
        ];
        if ($entityNamespace !== $namespace) {
            $imports[] = $entityFqcn;
        }

        sort($imports);
        $useClauses = implode("\n", array_map(static fn(string $fqcn): string => \sprintf('use %s;', $fqcn), $imports));

        $header = PhpHeader::render($namespace);
        $body = <<<PHP
            {$useClauses}

            /**
             * @extends CycleRepository<{$entityShortName}>
             */
            final class {$shortName} extends CycleRepository
            {
                public function __construct(ORMInterface \$orm, UnitOfWorkInterface \$unitOfWork)
                {
                    parent::__construct({$entityShortName}::class, \$orm, \$unitOfWork);
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->repositoryPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::Repository,
        );
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    private function shortNameOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
