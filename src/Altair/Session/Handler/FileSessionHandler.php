<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Handler;

use Altair\Filesystem\Filesystem;
use Carbon\Carbon;
use Override;
use ReturnTypeWillChange;
use SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * FileSessionHandler constructor.
     */
    public function __construct(protected Filesystem $filesystem, protected string $path, protected int $minutes) {}

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function close()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function destroy($session_id)
    {
        return $this->filesystem->delete($this->path . DIRECTORY_SEPARATOR . $session_id);
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function gc($maxlifetime): void
    {
        $files = $this->filesystem->listAllFiles($this->path);
        $now = time();
        foreach ($files as $file) {
            if (filemtime($file->getPathname()) + $maxlifetime <= $now) {
                $this->filesystem->delete($file->getPathname());
            }
        }
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function read($session_id)
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $session_id;

        return $this->filesystem->exists($path)
        && filemtime($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()
            ? $this->filesystem->get($path, true)
            : '';
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function write($session_id, $session_data)
    {
        return (bool) $this->filesystem->put($this->path . DIRECTORY_SEPARATOR . $session_id, $session_data, true);
    }
}
