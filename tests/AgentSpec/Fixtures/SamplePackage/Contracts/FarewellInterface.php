<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec\Fixtures\SamplePackage\Contracts;

interface FarewellInterface extends GreeterInterface
{
    public function bye(): string;
}
