<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\PredisCacheItemStorage;
use Predis\Client;

class PredisCacheItemStorageTest extends AbstractStorageTestCase
{
    protected function setUp()
    {
        $redis = new Client(['host' => 'localhost', 'port' => 6379]);

        $this->store = new PredisCacheItemStorage($redis, 'test');
    }
}
