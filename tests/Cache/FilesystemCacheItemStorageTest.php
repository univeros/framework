<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Filesystem\Filesystem;

class FilesystemCacheItemStorageTest extends AbstractStorageTestCase
{
    private Filesystem $fs;

    #[\Override]
    protected function setUp(): void    {
        $this->fs = new Filesystem();
        $this->fs->makeDirectory(__DIR__ . '/tmp');

        $this->store = new FilesystemCacheItemStorage($this->fs, __DIR__ . '/tmp');
        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void    {
        parent::tearDown();
        $this->fs->deleteDirectory(__DIR__ . '/tmp');
    }
}
