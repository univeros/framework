<?php

namespace Altair\Tests\Filesystem;

use Altair\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class FilesystemTest extends TestCase
{
    /**
     * @var string
     */
    private $tmpDir;
    /**
     * @var Filesystem
     */
    private $fs;

    public function setUp()
    {
        $this->tmpDir = __DIR__ . '/tmp';
        $this->fs = new Filesystem();
        $this->fs->makeDirectory($this->tmpDir);
    }

    public function tearDown()
    {
        $this->fs->deleteDirectory($this->tmpDir);
    }

    public function testGetsTheContentsOfAFile()
    {
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        $this->assertEquals('Hello World', $this->fs->get($this->tmpDir . '/file.txt'));
    }

    public function testGetShared()
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('Skipping since the pcntl extension is not available');
        }
        $content = str_repeat('123456', 1000000);
        $result = 1;
        for ($i = 1; $i <= 20; ++$i) {
            $pid = pcntl_fork();
            if (!$pid) {
                $files = new Filesystem;
                $files->put($this->tmpDir . '/file.txt', $content, true);
                $read = $files->get($this->tmpDir . '/file.txt', true);
                exit(strlen($read) === strlen($content) ? 1 : 0);
            }
        }
        while (pcntl_waitpid(0, $status) !== -1) {
            $status = pcntl_wexitstatus($status);
            $result *= $status;
        }
        $this->assertTrue($result === 1);
    }

    public function testReadLines()
    {
        file_put_contents($this->tmpDir . '/file.txt', "Hello\nWorld");
        $lines = $this->fs->readLines($this->tmpDir . '/file.txt');
        $this->assertCount(2, $lines);
    }

    public function testGetsRequiredFileValue()
    {
        file_put_contents($this->tmpDir . '/file.php', '<?php return [];');
        $value = $this->fs->getRequiredFileValue($this->tmpDir . '/file.php');
        $this->assertTrue(is_array($value));
    }

    public function testRequireOnceRequiresFilesProperly()
    {
        mkdir($this->tmpDir . '/foo');
        file_put_contents($this->tmpDir . '/foo/foo.php', '<?php function function_xyz(){};');
        $this->fs->requireOnce($this->tmpDir . '/foo/foo.php');
        file_put_contents($this->tmpDir . '/foo/foo.php', '<?php function function_xyz_changed(){};');
        $this->fs->requireOnce($this->tmpDir . '/foo/foo.php');
        $this->assertTrue(function_exists('function_xyz'));
        $this->assertFalse(function_exists('function_xyz_changed'));
    }

    public function testSetChmod()
    {
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        $this->fs->chmod($this->tmpDir . '/file.txt', 0755);
        $filePermission = substr(sprintf('%o', fileperms($this->tmpDir . '/file.txt')), -4);
        $this->assertEquals('0755', $filePermission);
    }

    public function testGetChmod()
    {
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        chmod($this->tmpDir . '/file.txt', 0755);

        $filePermission = $this->fs->chmod($this->tmpDir . '/file.txt');
        $this->assertEquals('0755', $filePermission);
    }

    public function testExists()
    {
        $this->assertTrue($this->fs->exists($this->tmpDir));
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        $this->assertTrue($this->fs->exists($this->tmpDir . '/file.txt'));
    }

    public function testMove()
    {
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');

        $this->fs->move($this->tmpDir . '/file.txt', $this->tmpDir . '/foo.txt');
        $this->assertFileExists($this->tmpDir . '/foo.txt');
        $this->assertFileNotExists($this->tmpDir . '/file.txt');
    }

    public function testPutContents()
    {
        $this->fs->put($this->tmpDir . '/file.txt', 'Hello World');

        $this->assertStringEqualsFile($this->tmpDir . '/file.txt', 'Hello World');
    }

    public function testPrependContents()
    {
        $this->fs->put($this->tmpDir . '/file.txt', 'World');
        $this->fs->prepend($this->tmpDir . '/file.txt', 'Hello ');
        $this->assertStringEqualsFile($this->tmpDir . '/file.txt', 'Hello World');
    }

    public function testAppendContents()
    {
        $this->fs->put($this->tmpDir . '/file.txt', 'Hello ');
        $this->fs->append($this->tmpDir . '/file.txt', 'World');
        $this->assertStringEqualsFile($this->tmpDir . '/file.txt', 'Hello World');
    }

    public function testDeletesFiles()
    {
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        $this->fs->delete($this->tmpDir . '/file.txt');
        $this->assertFalse($this->fs->exists($this->tmpDir . '/file.txt'));

        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        file_put_contents($this->tmpDir . '/bar.txt', 'bar');
        $this->fs->delete($this->tmpDir . '/foo.txt', $this->tmpDir . '/bar.txt');
        $this->assertFalse($this->fs->exists($this->tmpDir . '/foo.txt'));
        $this->assertFalse($this->fs->exists($this->tmpDir . '/bar.txt'));

        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        file_put_contents($this->tmpDir . '/bar.txt', 'bar');
        $this->fs->delete([$this->tmpDir . '/foo.txt', $this->tmpDir . '/bar.txt']);
        $this->assertFalse($this->fs->exists($this->tmpDir . '/foo.txt'));
        $this->assertFalse($this->fs->exists($this->tmpDir . '/bar.txt'));
    }

    public function testCopyFiles()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'Hello World');
        $this->fs->copy($this->tmpDir . '/foo.txt', $this->tmpDir . '/bar.txt');
        $this->assertTrue($this->fs->exists($this->tmpDir . '/bar.txt'));
        $this->assertStringEqualsFile($this->tmpDir . '/bar.txt', 'Hello World');
    }

    public function testLink()
    {
        file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        $this->fs->link($this->tmpDir . '/file.txt', $this->tmpDir . '/foo');
        $this->assertTrue(is_link($this->tmpDir . '/foo'));
        $this->assertEquals($this->tmpDir . '/file.txt', readlink($this->tmpDir . '/foo'));
        $this->assertStringEqualsFile($this->tmpDir . '/foo', 'Hello World');
    }

    public function testMakeDirectory()
    {
        $this->fs->makeDirectory($this->tmpDir . '/test');
        $this->assertTrue($this->fs->isDirectory($this->tmpDir . '/test'));
    }

    public function testMoveDirectory()
    {
        $this->fs->makeDirectory($this->tmpDir . '/foo');
        $this->fs->put($this->tmpDir . '/foo/file.txt', 'Hello World');
        $this->assertTrue($this->fs->moveDirectory($this->tmpDir . '/foo', $this->tmpDir . '/bar'));
        $this->assertFalse($this->fs->exists($this->tmpDir . '/foo'));
        $this->assertDirectoryExists($this->tmpDir . '/bar');
        $this->assertFileExists($this->tmpDir . '/bar/file.txt');
        $this->assertStringEqualsFile($this->tmpDir . '/bar/file.txt', 'Hello World');

        $this->fs->makeDirectory($this->tmpDir . '/foo');
        $this->fs->put($this->tmpDir . '/foo/file.txt', 'foo');
        $this->assertTrue($this->fs->moveDirectory($this->tmpDir . '/bar', $this->tmpDir . '/foo', true));
        $this->assertStringEqualsFile($this->tmpDir . '/foo/file.txt', 'Hello World');
    }

    public function testCopyDirectoryReturnsFalseIfSourceIsNotADirectory()
    {
        $files = new Filesystem;
        $this->assertFalse($files->copyDirectory($this->tmpDir . '/foo/bar', $this->tmpDir));
    }

    public function testCopyDirectoryMovesEntireDirectory()
    {
        mkdir($this->tmpDir . '/tmp', 0777, true);
        file_put_contents($this->tmpDir . '/tmp/foo.txt', '');
        file_put_contents($this->tmpDir . '/tmp/bar.txt', '');
        mkdir($this->tmpDir . '/tmp/nested', 0777, true);
        file_put_contents($this->tmpDir . '/tmp/nested/baz.txt', '');

        $this->fs->copyDirectory($this->tmpDir . '/tmp', $this->tmpDir . '/tmp2');
        $this->assertTrue(is_dir($this->tmpDir . '/tmp2'));
        $this->assertFileExists($this->tmpDir . '/tmp2/foo.txt');
        $this->assertFileExists($this->tmpDir . '/tmp2/bar.txt');
        $this->assertTrue(is_dir($this->tmpDir . '/tmp2/nested'));
        $this->assertFileExists($this->tmpDir . '/tmp2/nested/baz.txt');
    }

    public function testDeleteDirectory()
    {
        mkdir($this->tmpDir . '/foo');
        file_put_contents($this->tmpDir . '/foo/file.txt', 'Hello World');

        $this->fs->deleteDirectory($this->tmpDir . '/foo');
        $this->assertFalse(is_dir($this->tmpDir . '/foo'));
        $this->assertFileNotExists($this->tmpDir . '/foo/file.txt');
    }

    public function testClearDirectory()
    {
        mkdir($this->tmpDir . '/foo');
        file_put_contents($this->tmpDir . '/foo/file.txt', 'Hello World');

        $this->fs->clearDirectory($this->tmpDir . '/foo');
        $this->assertTrue(is_dir($this->tmpDir . '/foo'));
        $this->assertFileNotExists($this->tmpDir . '/foo/file.txt');
    }

    public function testGetFileNameHashSizeAndFileExtension()
    {
        $size = file_put_contents($this->tmpDir . '/file.txt', 'Hello World');
        $this->assertEquals('file', $this->fs->getFileName($this->tmpDir . '/file.txt'));
        $this->assertEquals($size, $this->fs->getFileSize($this->tmpDir . '/file.txt'));
        $hash = md5_file($this->tmpDir . '/file.txt');
        $this->assertEquals($hash, $this->fs->getFileHash($this->tmpDir . '/file.txt'));
        $this->assertEquals('txt', $this->fs->getFileExtension($this->tmpDir . '/file.txt'));
    }

    public function testBasenameReturnsBasename()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        $this->assertEquals('foo.txt', $this->fs->getFileBasename($this->tmpDir . '/foo.txt'));
    }

    public function testFileTypeAndFileMimeType()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        $this->assertEquals('file', $this->fs->getType($this->tmpDir . '/foo.txt'));
        $this->assertEquals('dir', $this->fs->getType($this->tmpDir));
        $this->assertEquals('text/plain', $this->fs->getFileMimeType($this->tmpDir . '/foo.txt'));
    }

    public function testIsWritable()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        @chmod($this->tmpDir . '/foo.txt', 0444);
        $this->assertFalse($this->fs->isWritable($this->tmpDir . '/foo.txt'));
        @chmod($this->tmpDir . '/foo.txt', 0777);
        $this->assertTrue($this->fs->isWritable($this->tmpDir . '/foo.txt'));
    }

    public function testIsReadable()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');

        // chmod is noneffective on Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertTrue($this->fs->isReadable($this->tmpDir . '/foo.txt'));
        } else {
            @chmod($this->tmpDir . '/foo.txt', 0000);
            $this->assertFalse($this->fs->isReadable($this->tmpDir . '/foo.txt'));
            @chmod($this->tmpDir . '/foo.txt', 0777);
            $this->assertTrue($this->fs->isReadable($this->tmpDir . '/foo.txt'));
        }
        $this->assertFalse($this->fs->isReadable($this->tmpDir . '/doesnotexist.txt'));
    }

    public function testGlobFindsFiles()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        file_put_contents($this->tmpDir . '/bar.txt', 'bar');
        $glob = $this->fs->glob($this->tmpDir . '/*.txt');
        $this->assertContains($this->tmpDir . '/foo.txt', $glob);
        $this->assertContains($this->tmpDir . '/bar.txt', $glob);
    }

    public function testListFilesFindsFiles()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        file_put_contents($this->tmpDir . '/bar.txt', 'bar');

        $allFiles = [];

        foreach ($this->fs->listFiles($this->tmpDir) as $file) {
            $allFiles[] = $file;
        }
        $this->assertContains($this->tmpDir . '/foo.txt', $allFiles);
        $this->assertContains($this->tmpDir . '/bar.txt', $allFiles);
    }

    public function testListAllFilesFindsAllFilesRecursive()
    {
        file_put_contents($this->tmpDir . '/foo.txt', 'foo');
        file_put_contents($this->tmpDir . '/bar.txt', 'bar');
        mkdir($this->tmpDir . '/tmp2');
        file_put_contents($this->tmpDir . '/tmp2/bar.txt', 'bar');

        $allFiles = [];

        /** @var SplFileInfo $file */
        foreach ($this->fs->listAllFiles($this->tmpDir, '/^.*\.txt/i', false) as $file) {
            $allFiles[] = $file->getPathname();
        }

        $this->assertContains($this->tmpDir . '/foo.txt', $allFiles);
        $this->assertContains($this->tmpDir . '/bar.txt', $allFiles);
        $this->assertContains($this->tmpDir . '/tmp2/bar.txt', $allFiles);
    }

    public function testListDirectoriesGetsAllDirectories()
    {
        mkdir($this->tmpDir . '/foo');
        mkdir($this->tmpDir . '/bar');

        $directories = $this->fs->listDirectories($this->tmpDir);
        $this->assertContains($this->tmpDir . '/foo', $directories);
        $this->assertContains($this->tmpDir . '/bar', $directories);
    }
}
