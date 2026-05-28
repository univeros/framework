<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Support\Fixtures;

/** An event listener fixture — registered as [object, 'handle'] in tests. */
final class SampleListener
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}
