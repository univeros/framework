<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures\Scanner;

// An anonymous class appears before the named class declaration — the scanner
// must skip it and still resolve the real, named class below.
$factory = static fn(): object => new class () {
    public int $value = 1;
};

final class AnonBeforeNamed
{
}
