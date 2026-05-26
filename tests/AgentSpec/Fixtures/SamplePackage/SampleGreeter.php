<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec\Fixtures\SamplePackage;

use Altair\Tests\AgentSpec\Fixtures\SamplePackage\Contracts\GreeterInterface;

final class SampleGreeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return self::DEFAULT_GREETING . ', ' . $name;
    }
}
