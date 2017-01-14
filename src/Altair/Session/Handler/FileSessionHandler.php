<?php
namespace Altair\Session\Handler;

use Altair\Filesystem\Filesystem;
use Carbon\Carbon;
use SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var string
     */
    protected $path;
    /**
     * @var int
     */
    protected $minutes;

    /**
     * FileSessionHandler constructor.
     *
     * @param Filesystem $filesystem
     * @param string $path
     * @param int $minutes
     */
    public function __construct(Filesystem $filesystem, string $path, int $minutes)
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->minutes = $minutes;
    }

    /**
     * @inheritdoc
     */
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function destroy($session_id)
    {
        return $this->filesystem->delete($this->path . DIRECTORY_SEPARATOR . $session_id);
    }

    /**
     * @inheritdoc
     */
    public function gc($maxlifetime)
    {
        $files = $this->filesystem->listAllFiles($this->path);
        $now = time();
        foreach ($files as $file) {
            if (filemtime($file) + $maxlifetime <= $now) {
                $this->filesystem->delete($file);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function read($session_id)
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $session_id;

        return $this->filesystem->exists($path)
        && filemtime($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()
            ? $this->filesystem->get($path, true)
            : '';

    }

    /**
     * @inheritdoc
     */
    public function write($session_id, $session_data)
    {
        return (bool)$this->filesystem->put($this->path . DIRECTORY_SEPARATOR . $session_id, $session_data, true);
    }
}
