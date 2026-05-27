<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;

/**
 * Runs every emitter against a Spec and returns the list of EmittedFile
 * results. Pure: nothing is written to disk here.
 *
 * Persistence-related emitters only fire when the Spec carries a
 * `persistence:` block.
 */
class EmissionPlan
{
    public function __construct(
        private readonly ActionEmitter $actionEmitter = new ActionEmitter(),
        private readonly InputEmitter $inputEmitter = new InputEmitter(),
        private readonly ResponderEmitter $responderEmitter = new ResponderEmitter(),
        private readonly DomainStubEmitter $domainEmitter = new DomainStubEmitter(),
        private readonly TestEmitter $testEmitter = new TestEmitter(),
        private readonly OpenApiEmitter $openApiEmitter = new OpenApiEmitter(),
        private readonly RouteEmitter $routeEmitter = new RouteEmitter(),
        private readonly EntityEmitter $entityEmitter = new EntityEmitter(),
        private readonly RepositoryEmitter $repositoryEmitter = new RepositoryEmitter(),
        private readonly MigrationEmitter $migrationEmitter = new MigrationEmitter(),
    ) {}

    /**
     * @return list<EmittedFile>
     */
    public function build(Spec $spec): array
    {
        $files = [
            $this->actionEmitter->emit($spec),
            $this->inputEmitter->emit($spec),
            $this->responderEmitter->emit($spec),
            $this->domainEmitter->emit($spec),
            $this->testEmitter->emit($spec),
            $this->openApiEmitter->emit($spec),
            $this->routeEmitter->emit($spec),
        ];

        if ($spec->persistence instanceof PersistenceSpec) {
            $files[] = $this->entityEmitter->emit($spec);
            if ($spec->persistence->repository !== '') {
                $files[] = $this->repositoryEmitter->emit($spec);
            }

            $files[] = $this->migrationEmitter->emit($spec);
        }

        return $files;
    }
}
