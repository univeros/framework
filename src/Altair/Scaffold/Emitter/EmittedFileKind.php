<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

enum EmittedFileKind: string
{
    case Action = 'action';
    case Input = 'input';
    case Responder = 'responder';
    case DomainStub = 'domain';
    case Test = 'test';
    case OpenApi = 'openapi';
    case Route = 'route';
    case Entity = 'entity';
    case Repository = 'repository';
    case Migration = 'migration';
}
