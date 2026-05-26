<?php

declare(strict_types=1);

namespace Altair\Tests\Common\Registry;

use Altair\Common\Contracts\RegistryInterface;
use Altair\Common\Registry\ArrayRegistry;
use PHPUnit\Framework\TestCase;

class ArrayRegistryTest extends TestCase
{
    public function testGetReturnsStoredValue(): void
    {
        $registry = new ArrayRegistry(['name' => 'alice']);

        $this->assertSame('alice', $registry->get('name'));
    }

    public function testGetUsesDotPath(): void
    {
        $registry = new ArrayRegistry(['user' => ['name' => 'alice']]);

        $this->assertSame('alice', $registry->get('user.name'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $registry = new ArrayRegistry();

        $this->assertSame('fallback', $registry->get('missing', 'fallback'));
    }

    public function testSetReturnsRegistryForFluency(): void
    {
        $registry = new ArrayRegistry();

        $result = $registry->set('foo', 'bar');

        $this->assertSame($registry, $result);
        $this->assertSame('bar', $registry->get('foo'));
    }

    public function testImplementsRegistryInterface(): void
    {
        $this->assertInstanceOf(RegistryInterface::class, new ArrayRegistry());
    }
}
