<?php

declare(strict_types=1);

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\PredisCacheItemStorage;
use Predis\Client;

class PredisCacheItemStorageTest extends AbstractStorageTestCase
{
    #[\Override]
    protected function setUp(): void    {
        $redis = new Client(['host' => 'localhost', 'port' => 6379]);

        $this->store = new PredisCacheItemStorage($redis, 'test');
    }
}
