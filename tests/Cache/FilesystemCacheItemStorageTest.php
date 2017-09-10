<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Filesystem\Filesystem;

class FilesystemCacheItemStorageTest extends AbstractStorageTestCase
{
    /**
     * @var Filesystem
     */
    private $fs;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->fs->makeDirectory(__DIR__ . '/tmp');
        $this->store = new FilesystemCacheItemStorage($this->fs, __DIR__ . '/tmp');
        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->fs->deleteDirectory(__DIR__ . '/tmp');
    }
}
