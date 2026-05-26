<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec\Fixtures\SamplePackage\Contracts;

interface GreeterInterface
{
    public const string DEFAULT_GREETING = 'hello';

    public function greet(string $name): string;
}
