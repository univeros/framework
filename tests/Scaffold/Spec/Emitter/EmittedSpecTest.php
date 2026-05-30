<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Spec\Emitter\EmittedSpec;
use PHPUnit\Framework\TestCase;

final class EmittedSpecTest extends TestCase
{
    public function testExposesPathAndContents(): void
    {
        $emitted = new EmittedSpec(relativePath: 'api/users/create.yaml', contents: 'endpoint: {}');

        self::assertSame('api/users/create.yaml', $emitted->relativePath);
        self::assertSame('endpoint: {}', $emitted->contents);
    }
}
