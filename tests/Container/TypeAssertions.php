<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Container\Typing;

use Altair\Container\Container;
use Altair\Tests\Container\Dependency;
use Altair\Tests\Container\NeedsDependency;

use function PHPStan\Testing\assertType;

$container = new Container();

assertType(Dependency::class, $container->make(Dependency::class));
assertType(NeedsDependency::class, $container->make(NeedsDependency::class));
