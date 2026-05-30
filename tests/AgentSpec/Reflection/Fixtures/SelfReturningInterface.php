<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\AgentSpec\Reflection\Fixtures;

/**
 * Fixture for #158: exercises the `self` / `static` return-type rendering
 * paths whose behaviour varies across PHP minor releases.
 */
interface SelfReturningInterface
{
    public function withSelf(): self;

    public function withStatic(): static;

    public function merge(self $other): self;

    public function maybeSelf(): ?self;

    public function name(): string;
}
